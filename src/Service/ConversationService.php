<?php

declare(strict_types=1);

namespace Amro\Service;

use Amro\Prompt\PromptBuilder;
use PDO;

class ConversationService
{
    private const HISTORY_LIMIT = 20;

    public function __construct(
        private PDO $db,
        private PromptBuilder $promptBuilder,
        private AIAgentService $agent,
    ) {}

    public function handleIncoming(string $phone, string $text): array
    {
        $phone = preg_replace('/\D/', '', $phone);

        if ($this->isPaused($phone)) {
            return [
                'reply'   => null,
                'paused'  => true,
                'reason'  => 'ia_paused (escalation prévia)',
            ];
        }

        if ($this->isOptedOut($phone)) {
            return [
                'reply'   => null,
                'paused'  => true,
                'reason'  => 'opt_out',
            ];
        }

        $this->logIncoming($phone, $text);

        $clienteNome = $this->getKnownClientName($phone);
        $systemPrompt = $this->promptBuilder->build() . "\n\n" . $this->promptBuilder->buildContext($phone, $clienteNome);

        $history = $this->loadHistory($phone);
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $history,
            [['role' => 'user', 'content' => $text]]
        );

        $this->saveMessage($phone, 'user', $text);

        $startedAt = microtime(true);
        $result = $this->agent->run($messages);
        $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);

        $reply = $result['reply'] ?? '';
        $action = $this->extractAction($reply);

        if ($reply !== '') {
            $this->saveMessage($phone, 'assistant', $reply, $result['tool_calls'] ?? null);
            $this->logOutgoing($phone, $reply, $action, $result, $latencyMs);
        }

        $this->touchConversation($phone, $clienteNome);

        return [
            'reply'      => $reply,
            'action'     => $action,
            'tool_calls' => $result['tool_calls'] ?? [],
            'usage'      => $result['usage'] ?? [],
            'latency_ms' => $latencyMs,
            'paused'     => $this->isPaused($phone),
        ];
    }

    private function isPaused(string $phone): bool
    {
        $stmt = $this->db->prepare('SELECT ia_paused FROM conversations WHERE phone = ?');
        $stmt->execute([$phone]);
        return ((int) $stmt->fetchColumn()) === 1;
    }

    private function isOptedOut(string $phone): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM unsubscribed_phones WHERE phone = ?');
        $stmt->execute([$phone]);
        return (bool) $stmt->fetchColumn();
    }

    private function loadHistory(string $phone): array
    {
        $stmt = $this->db->prepare(
            'SELECT role, content, tool_calls, tool_call_id FROM chat_memory
             WHERE session_id = ? ORDER BY id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $phone);
        $stmt->bindValue(2, self::HISTORY_LIMIT, PDO::PARAM_INT);
        $stmt->execute();
        $rows = array_reverse($stmt->fetchAll());
        $msgs = [];
        foreach ($rows as $r) {
            $m = ['role' => $r['role'], 'content' => $r['content']];
            if ($r['tool_call_id']) {
                $m['tool_call_id'] = $r['tool_call_id'];
            }
            if ($r['tool_calls']) {
                $m['tool_calls'] = json_decode($r['tool_calls'], true);
            }
            $msgs[] = $m;
        }
        return $msgs;
    }

    private function saveMessage(string $phone, string $role, string $content, ?array $toolCalls = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO chat_memory (session_id, role, content, tool_calls) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $phone,
            $role,
            $content,
            $toolCalls ? json_encode($toolCalls, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    private function logIncoming(string $phone, string $text): void
    {
        $this->db->prepare(
            'INSERT INTO conversation_logs (phone, direction, message) VALUES (?, ?, ?)'
        )->execute([$phone, 'inbound', $text]);
    }

    private function logOutgoing(string $phone, string $reply, ?string $action, array $result, int $latencyMs): void
    {
        $this->db->prepare(
            'INSERT INTO conversation_logs (phone, direction, message, ai_action, tool_calls, tokens_input, tokens_output, model, latency_ms)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $phone,
            'outbound',
            $reply,
            $action,
            json_encode($result['tool_calls'] ?? [], JSON_UNESCAPED_UNICODE),
            $result['usage']['prompt_tokens'] ?? 0,
            $result['usage']['completion_tokens'] ?? 0,
            $_ENV['OPENAI_MODEL'] ?? null,
            $latencyMs,
        ]);
    }

    private function touchConversation(string $phone, ?string $nome): void
    {
        $this->db->prepare(
            'INSERT INTO conversations (phone, nome, last_message_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                last_message_at = NOW(),
                nome = COALESCE(VALUES(nome), nome)'
        )->execute([$phone, $nome]);
    }

    private function getKnownClientName(string $phone): ?string
    {
        $stmt = $this->db->prepare('SELECT nome FROM conversations WHERE phone = ?');
        $stmt->execute([$phone]);
        $n = $stmt->fetchColumn();
        return $n ?: null;
    }

    private function extractAction(string $reply): ?string
    {
        if (preg_match('/\[(STATUS_RESPONDIDO|RASTREIO_ENTREGUE|ESTOQUE_RESPONDIDO|PASSAR_HUMANO|PRAZO_RESPONDIDO)\]/u', $reply, $m)) {
            return $m[1];
        }
        return null;
    }
}

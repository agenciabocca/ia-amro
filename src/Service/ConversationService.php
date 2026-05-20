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
        private ?RateLimiterService $rateLimiter = null,
    ) {}

    public function handleIncoming(string $phone, string $text, ?string $messageId = null): array
    {
        $phone = preg_replace('/\D/', '', $phone);
        $text = trim($text);

        if (filter_var($_ENV['AI_DISABLED'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return ['reply' => null, 'paused' => true, 'reason' => 'ai_disabled_kill_switch'];
        }

        if ($messageId && $this->isDuplicate($messageId)) {
            return ['reply' => null, 'paused' => true, 'reason' => 'duplicate_message_id'];
        }

        if ($this->detectOptOut($text)) {
            $this->markOptedOut($phone, $text);
            $this->logIncoming($phone, $text, $messageId);
            return [
                'reply'  => 'Pronto, você foi removida da minha lista. Se mudar de ideia, é só me chamar de novo. ☺️',
                'paused' => true,
                'reason' => 'opt_out_acknowledged',
            ];
        }

        if ($this->isPaused($phone)) {
            $this->logIncoming($phone, $text, $messageId);
            return [
                'reply'   => null,
                'paused'  => true,
                'reason'  => 'ia_paused (escalation prévia ou conversa com humana)',
            ];
        }

        if ($this->isOptedOut($phone)) {
            return ['reply' => null, 'paused' => true, 'reason' => 'opt_out'];
        }

        if ($this->rateLimiter) {
            $rl = $this->rateLimiter->check($phone);
            if (!$rl['allowed']) {
                $this->logIncoming($phone, $text, $messageId);
                return [
                    'reply'  => null,
                    'paused' => true,
                    'reason' => "rate_limited ({$rl['count']}/{$rl['limit']} em {$rl['window_seconds']}s)",
                ];
            }
        }

        $this->logIncoming($phone, $text, $messageId);

        if ($messageId) {
            $this->markProcessed($messageId, $phone);
        }

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
            'SELECT role, content FROM chat_memory
             WHERE session_id = ? AND role IN ("user","assistant") AND content <> ""
             ORDER BY id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $phone);
        $stmt->bindValue(2, self::HISTORY_LIMIT, PDO::PARAM_INT);
        $stmt->execute();
        $rows = array_reverse($stmt->fetchAll());
        return array_map(
            fn($r) => ['role' => $r['role'], 'content' => $r['content']],
            $rows
        );
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

    private function logIncoming(string $phone, string $text, ?string $messageId = null): void
    {
        $this->db->prepare(
            'INSERT INTO conversation_logs (phone, direction, message, message_id) VALUES (?, ?, ?, ?)'
        )->execute([$phone, 'inbound', $text, $messageId]);
    }

    private function isDuplicate(string $messageId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM processed_messages WHERE message_id = ?');
        $stmt->execute([$messageId]);
        return (bool) $stmt->fetchColumn();
    }

    private function markProcessed(string $messageId, string $phone): void
    {
        $this->db->prepare(
            'INSERT IGNORE INTO processed_messages (message_id, phone) VALUES (?, ?)'
        )->execute([$messageId, $phone]);
    }

    private function detectOptOut(string $text): bool
    {
        $norm = mb_strtolower(trim($text));
        $patterns = [
            '/^(parar|sair|cancelar|stop|unsubscribe|cancela)\s*[\.\!]?$/u',
            '/n[aã]o\s+quero\s+(mais|receber)/u',
            '/me\s+(tira|remova|retira)\s+(da|dessa)/u',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $norm)) {
                return true;
            }
        }
        return false;
    }

    private function markOptedOut(string $phone, string $reason): void
    {
        $this->db->prepare(
            'INSERT IGNORE INTO unsubscribed_phones (phone, reason) VALUES (?, ?)'
        )->execute([$phone, mb_substr($reason, 0, 250)]);
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

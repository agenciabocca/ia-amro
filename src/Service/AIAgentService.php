<?php

declare(strict_types=1);

namespace Amro\Service;

use Amro\Integration\OpenAIClient;
use Amro\Tool\ToolRegistry;

class AIAgentService
{
    public function __construct(
        private OpenAIClient $ai,
        private ToolRegistry $registry,
        private int $maxIterations = 3,
    ) {}

    public function run(array $messages): array
    {
        $tools = $this->registry->getOpenAIDefinitions();
        $iteration = 0;
        $totalUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        $toolCallsTrace = [];

        while ($iteration < $this->maxIterations) {
            $iteration++;
            $resp = $this->ai->chat($messages, $tools);

            $usage = $resp['usage'] ?? [];
            foreach (['prompt_tokens', 'completion_tokens', 'total_tokens'] as $k) {
                $totalUsage[$k] += (int) ($usage[$k] ?? 0);
            }

            $choice = $resp['choices'][0] ?? null;
            if (!$choice) {
                return ['error' => 'sem choice na resposta', 'iterations' => $iteration];
            }

            $msg = $choice['message'];

            $toolCalls = $msg['tool_calls'] ?? [];
            if ($toolCalls) {
                foreach ($toolCalls as $i => $tc) {
                    if (!isset($tc['type'])) {
                        $msg['tool_calls'][$i]['type'] = 'function';
                    }
                }
            }
            $messages[] = $msg;

            if (empty($toolCalls)) {
                return [
                    'reply'         => $msg['content'] ?? '',
                    'iterations'    => $iteration,
                    'tool_calls'    => $toolCallsTrace,
                    'usage'         => $totalUsage,
                    'finish_reason' => $choice['finish_reason'] ?? null,
                ];
            }

            foreach ($toolCalls as $tc) {
                $name = $tc['function']['name'];
                $args = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
                $toolCallsTrace[] = ['tool' => $name, 'args' => $args];

                try {
                    $result = $this->registry->execute($name, $args);
                } catch (\Throwable $e) {
                    $result = ['error' => $e->getMessage()];
                }

                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $tc['id'],
                    'content'      => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        return [
            'reply'      => '(IA atingiu limite de iterações sem resposta final)',
            'iterations' => $iteration,
            'tool_calls' => $toolCallsTrace,
            'usage'      => $totalUsage,
            'warning'    => 'max_iterations_reached',
        ];
    }
}

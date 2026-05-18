<?php

declare(strict_types=1);

namespace Amro\Integration;

use RuntimeException;

class OpenAIClient
{
    private const BASE_URL = 'https://api.openai.com/v1';

    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'gpt-4o-mini')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        $payload = array_merge([
            'model'    => $this->model,
            'messages' => $messages,
            'temperature' => 0.3,
        ], $options);

        if ($tools) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = $payload['tool_choice'] ?? 'auto';
        }

        return $this->request('POST', '/chat/completions', $payload);
    }

    private function request(string $method, string $path, array $body): array
    {
        $url = self::BASE_URL . $path;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
        ]);

        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            throw new RuntimeException("OpenAI cURL error: {$err}");
        }

        $decoded = json_decode($resp, true);

        if ($status === 429) {
            sleep(2);
            return $this->request($method, $path, $body);
        }

        if ($status >= 400) {
            $msg = $decoded['error']['message'] ?? $resp;
            throw new RuntimeException("OpenAI {$status}: " . $msg);
        }

        return $decoded ?? [];
    }
}

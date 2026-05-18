<?php

declare(strict_types=1);

namespace Amro\Integration;

use RuntimeException;

class WoocommerceClient
{
    private string $baseUrl;
    private string $authHeader;

    public function __construct(string $baseUrl, string $consumerKey, string $consumerSecret)
    {
        $this->baseUrl = rtrim($baseUrl, '/') . '/wp-json/wc/v3';
        $this->authHeader = 'Basic ' . base64_encode($consumerKey . ':' . $consumerSecret);
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    private function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $url = $this->baseUrl . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init();
        $headers = [
            'Authorization: ' . $this->authHeader,
            'Accept: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            throw new RuntimeException("WC cURL error: {$err}");
        }
        if ($status >= 400) {
            throw new RuntimeException("WC {$status}: " . $resp);
        }

        return json_decode($resp, true) ?? [];
    }
}

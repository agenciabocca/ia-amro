<?php

declare(strict_types=1);

namespace Amro\Integration;

use RuntimeException;

class MelhorEnvioClient
{
    private string $baseUrl;
    private string $token;

    public function __construct(string $baseUrl, string $token)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    public function post(string $path, array $body): array
    {
        return $this->request('POST', $path, [], $body);
    }

    public function getOrders(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $query = array_merge($filters, ['page' => $page, 'per_page' => $perPage]);
        return $this->get('/me/orders', $query);
    }

    public function findOrdersByCustomerName(string $name): array
    {
        return $this->getOrders(['q' => $name]);
    }

    public function getTracking(array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }
        return $this->post('/me/shipment/tracking', ['orders' => $orderIds]);
    }

    public function getOrderDetail(string $orderId): array
    {
        return $this->get('/me/orders/' . $orderId);
    }

    private function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $url = $this->baseUrl . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init();
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Accept: application/json',
            'User-Agent: AMRO Fardamentos IA Suporte (douglas@agenciabocca.com.br)',
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
            throw new RuntimeException("ME cURL error: {$err}");
        }
        if ($status >= 400) {
            throw new RuntimeException("ME {$status}: " . $resp);
        }

        return json_decode($resp, true) ?? [];
    }
}

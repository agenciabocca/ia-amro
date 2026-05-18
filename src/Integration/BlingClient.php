<?php

declare(strict_types=1);

namespace Amro\Integration;

use PDO;
use RuntimeException;

class BlingClient
{
    private const BASE_URL = 'https://api.bling.com.br/Api/v3';
    private const TOKEN_URL = 'https://api.bling.com.br/Api/v3/oauth/token';

    private PDO $db;
    private string $clientId;
    private string $clientSecret;
    private ?string $accessToken = null;
    private ?int $expiresAt = null;

    public function __construct(PDO $db, string $clientId, string $clientSecret)
    {
        $this->db = $db;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->loadTokenFromDb();
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    public function post(string $path, array $body): array
    {
        return $this->request('POST', $path, [], $body);
    }

    public function put(string $path, array $body): array
    {
        return $this->request('PUT', $path, [], $body);
    }

    public function patch(string $path, array $body = []): array
    {
        return $this->request('PATCH', $path, [], $body);
    }

    private function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $this->ensureValidToken();

        $url = self::BASE_URL . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init();
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
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
            throw new RuntimeException("Bling cURL error: {$err}");
        }

        $decoded = json_decode($resp, true);

        if ($status === 401 && isset($decoded['error']['type']) && $decoded['error']['type'] === 'invalid_token') {
            $this->refreshAccessToken();
            return $this->request($method, $path, $query, $body);
        }

        if ($status === 429) {
            sleep(2);
            return $this->request($method, $path, $query, $body);
        }

        if ($status >= 400) {
            throw new RuntimeException("Bling {$status}: " . ($resp ?: 'no body'));
        }

        return $decoded ?? [];
    }

    private function ensureValidToken(): void
    {
        if (!$this->accessToken || !$this->expiresAt || $this->expiresAt < time() + 60) {
            $this->refreshAccessToken();
        }
    }

    private function refreshAccessToken(): void
    {
        $refresh = $this->getRefreshTokenFromDb();
        if (!$refresh) {
            throw new RuntimeException('Bling refresh_token ausente. Faça OAuth inicial.');
        }

        $ch = curl_init(self::TOKEN_URL);
        $basic = base64_encode($this->clientId . ':' . $this->clientSecret);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . $basic,
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh,
            ]),
        ]);

        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($status !== 200) {
            throw new RuntimeException("Bling token refresh falhou [{$status}]: {$resp} {$err}");
        }

        $data = json_decode($resp, true);
        $this->accessToken = $data['access_token'];
        $this->expiresAt = time() + (int) $data['expires_in'];

        $this->saveTokenToDb(
            $data['access_token'],
            $data['refresh_token'] ?? $refresh,
            $this->expiresAt
        );
    }

    private function loadTokenFromDb(): void
    {
        $row = $this->db->query('SELECT access_token, expires_at FROM bling_token WHERE id = 1')
                        ->fetch();
        if ($row) {
            $this->accessToken = $row['access_token'];
            $this->expiresAt = strtotime($row['expires_at']);
        }
    }

    private function getRefreshTokenFromDb(): ?string
    {
        $row = $this->db->query('SELECT refresh_token FROM bling_token WHERE id = 1')->fetch();
        if ($row && $row['refresh_token']) {
            return $row['refresh_token'];
        }
        return $_ENV['BLING_REFRESH_TOKEN'] ?? null;
    }

    private function saveTokenToDb(string $accessToken, string $refreshToken, int $expiresAt): void
    {
        $expiresAtSql = date('Y-m-d H:i:s', $expiresAt);
        $sql = 'INSERT INTO bling_token (id, access_token, refresh_token, expires_at)
                VALUES (1, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    access_token = VALUES(access_token),
                    refresh_token = VALUES(refresh_token),
                    expires_at = VALUES(expires_at)';
        $this->db->prepare($sql)->execute([$accessToken, $refreshToken, $expiresAtSql]);
    }
}

<?php

declare(strict_types=1);

namespace Amro\Service;

use PDO;

class RateLimiterService
{
    public function __construct(
        private PDO $db,
        private int $maxMessages = 12,
        private int $windowSeconds = 60,
    ) {}

    public function check(string $phone): array
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM conversation_logs
             WHERE phone = ? AND direction = "inbound"
             AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->bindValue(1, $phone);
        $stmt->bindValue(2, $this->windowSeconds, PDO::PARAM_INT);
        $stmt->execute();
        $count = (int) $stmt->fetchColumn();

        return [
            'allowed'        => $count <= $this->maxMessages,
            'count'          => $count,
            'limit'          => $this->maxMessages,
            'window_seconds' => $this->windowSeconds,
        ];
    }
}

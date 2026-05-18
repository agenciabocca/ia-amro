<?php

require __DIR__ . '/../src/bootstrap.php';

use Amro\Integration\MelhorEnvioClient;

$me = new MelhorEnvioClient($_ENV['ME_BASE_URL'], $_ENV['ME_ACCESS_TOKEN']);

echo "== Tenta diferentes filtros para achar pedido com tracking ==\n\n";

$filters = [
    ['status' => 'posted'],
    ['status' => 'delivered'],
    ['status' => 'released'],
];

foreach ($filters as $f) {
    echo "--- filter: " . json_encode($f) . " ---\n";
    try {
        $r = $me->getOrders($f, 1, 2);
        $count = is_array($r['data'] ?? null) ? count($r['data']) : 0;
        echo "  count: {$count}\n";
        if ($count > 0) {
            foreach ($r['data'] as $o) {
                echo "    id=" . substr($o['id'], 0, 8) . "... | protocol={$o['protocol']} | status={$o['status']}\n";
                echo "      tracking='{$o['tracking']}' posted_at=" . ($o['posted_at'] ?? '?') . "\n";
                echo "      to.name='{$o['to']['name']}'\n";
                echo "      additional_info: " . json_encode($o['additional_info'] ?? null) . "\n";
                echo "      tags: " . json_encode($o['tags'] ?? []) . "\n";
            }
        }
    } catch (Throwable $e) {
        echo "  ERRO: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// Tenta endpoint search específico
echo "--- /me/orders/search ?status=posted ---\n";
try {
    $r = $me->get('/me/orders/search', ['status' => 'posted', 'per_page' => 2]);
    $count = is_array($r['data'] ?? null) ? count($r['data']) : 0;
    echo "  count: {$count}\n";
    if ($count > 0) {
        foreach ($r['data'] as $o) {
            echo "    id=" . substr($o['id'], 0, 8) . "... | status={$o['status']} | tracking='" . ($o['tracking'] ?? '') . "'\n";
        }
    }
} catch (Throwable $e) {
    echo "  ERRO: " . $e->getMessage() . "\n";
}

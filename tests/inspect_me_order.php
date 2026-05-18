<?php

require __DIR__ . '/../src/bootstrap.php';

use Amro\Integration\MelhorEnvioClient;

$me = new MelhorEnvioClient($_ENV['ME_BASE_URL'], $_ENV['ME_ACCESS_TOKEN']);

echo "== Últimos 3 pedidos com detalhes (campos chave) ==\n\n";
$r = $me->getOrders([], 1, 3);
foreach ($r['data'] ?? [] as $i => $o) {
    echo "--- Pedido " . ($i + 1) . " ---\n";
    echo "id:           " . ($o['id'] ?? '') . "\n";
    echo "protocol:     " . ($o['protocol'] ?? '') . "\n";
    echo "status:       " . ($o['status'] ?? '') . "\n";
    echo "tracking:     " . ($o['tracking'] ?? '(vazio)') . "\n";
    echo "self_tracking:" . ($o['self_tracking'] ?? '(vazio)') . "\n";
    echo "service.name: " . ($o['service']['name'] ?? '') . "\n";
    echo "created_at:   " . ($o['created_at'] ?? '') . "\n";
    echo "paid_at:      " . ($o['paid_at'] ?? '(vazio)') . "\n";
    echo "generated_at: " . ($o['generated_at'] ?? '(vazio)') . "\n";
    echo "posted_at:    " . ($o['posted_at'] ?? '(vazio)') . "\n";
    echo "delivered_at: " . ($o['delivered_at'] ?? '(vazio)') . "\n";
    echo "to.name:      " . ($o['to']['name'] ?? '') . "\n";
    echo "to.cep:       " . ($o['to']['postal_code'] ?? '') . "\n";
    echo "purchase_id:  " . ($o['purchase_id'] ?? '(vazio)') . "\n";
    echo "additional_info: " . substr((string) ($o['additional_info'] ?? ''), 0, 120) . "\n";
    echo "tags: " . json_encode($o['tags'] ?? []) . "\n";
    echo "products[0]: " . (isset($o['products'][0]) ? json_encode($o['products'][0]) : '(vazio)') . "\n";
    echo "\n";
}

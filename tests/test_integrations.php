<?php

require __DIR__ . '/../src/bootstrap.php';

use Amro\Integration\BlingClient;
use Amro\Integration\MelhorEnvioClient;
use Amro\Integration\WoocommerceClient;
use Amro\Integration\OpenAIClient;

$db = app_db();

echo "== Bling: produto pai SKU 75793 (Scrubs Letícia) ==\n";
try {
    $bling = new BlingClient($db, $_ENV['BLING_CLIENT_ID'], $_ENV['BLING_CLIENT_SECRET']);
    $r = $bling->get('/produtos', ['codigo' => '75793', 'limite' => 1]);
    $p = $r['data'][0] ?? null;
    if ($p) {
        echo "  OK  codigo={$p['codigo']} id={$p['id']} nome={$p['nome']}\n";
    } else {
        echo "  vazio\n";
    }
} catch (Throwable $e) {
    echo "  ERRO: " . $e->getMessage() . "\n";
}

echo "\n== Melhor Envio: GET /me ==\n";
try {
    $me = new MelhorEnvioClient($_ENV['ME_BASE_URL'], $_ENV['ME_ACCESS_TOKEN']);
    $info = $me->get('/me');
    echo "  OK  id=" . ($info['id'] ?? '?') . " email=" . ($info['email'] ?? '?') . "\n";
} catch (Throwable $e) {
    echo "  ERRO: " . $e->getMessage() . "\n";
}

echo "\n== Melhor Envio: últimos 5 pedidos ==\n";
try {
    $r = $me->getOrders([], 1, 5);
    $count = is_array($r['data'] ?? null) ? count($r['data']) : 0;
    echo "  Pedidos retornados: {$count}\n";
    if ($count > 0) {
        $o = $r['data'][0];
        echo "  Primeiro: id=" . ($o['id'] ?? '?') . " protocol=" . ($o['protocol'] ?? '?') .
             " status=" . ($o['status'] ?? '?') . " tracking=" . ($o['tracking'] ?? '(sem)') . "\n";
        echo "  CAMPOS DISPONÍVEIS: " . implode(', ', array_keys($o)) . "\n";
    }
} catch (Throwable $e) {
    echo "  ERRO: " . $e->getMessage() . "\n";
}

echo "\n== WC: produto pai 75793 ==\n";
try {
    $wc = new WoocommerceClient($_ENV['WC_BASE_URL'], $_ENV['WC_CONSUMER_KEY'], $_ENV['WC_CONSUMER_SECRET']);
    $r = $wc->get('/products', ['sku' => '75793']);
    if (!empty($r[0])) {
        echo "  OK  id={$r[0]['id']} name={$r[0]['name']}\n";
    } else {
        echo "  vazio\n";
    }
} catch (Throwable $e) {
    echo "  ERRO: " . $e->getMessage() . "\n";
}

echo "\n== OpenAI: ping chat ==\n";
try {
    $ai = new OpenAIClient($_ENV['OPENAI_API_KEY'], $_ENV['OPENAI_MODEL']);
    $r = $ai->chat([
        ['role' => 'system', 'content' => 'Responda em 1 palavra.'],
        ['role' => 'user', 'content' => 'Você está funcionando? Responda com "ok" ou "nao".'],
    ], [], ['max_tokens' => 5]);
    $msg = $r['choices'][0]['message']['content'] ?? '?';
    echo "  OK  resposta: '{$msg}' (tokens={$r['usage']['total_tokens']})\n";
} catch (Throwable $e) {
    echo "  ERRO: " . $e->getMessage() . "\n";
}

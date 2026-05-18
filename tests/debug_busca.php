<?php

require __DIR__ . '/../src/bootstrap.php';

use Amro\Integration\BlingClient;

$bling = new BlingClient(app_db(), $_ENV['BLING_CLIENT_ID'], $_ENV['BLING_CLIENT_SECRET']);

foreach (['Scrubs Letícia', 'Blusa Ysa Bazar', 'Blusa Ysa'] as $q) {
    echo "== /produtos?pesquisa='$q' (limite 10) ==\n";
    $r = $bling->get('/produtos', ['pesquisa' => $q, 'limite' => 10]);
    foreach ($r['data'] ?? [] as $p) {
        echo "  {$p['codigo']} | {$p['nome']}\n";
    }
    echo "\n";
}

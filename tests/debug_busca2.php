<?php

require __DIR__ . '/../src/bootstrap.php';

use Amro\Integration\BlingClient;

$bling = new BlingClient(app_db(), $_ENV['BLING_CLIENT_ID'], $_ENV['BLING_CLIENT_SECRET']);

echo "== variante 1: pesquisa ==\n";
$r = $bling->get('/produtos', ['pesquisa' => 'Letícia', 'limite' => 5]);
foreach ($r['data'] ?? [] as $p) echo "  {$p['codigo']} | {$p['nome']}\n";

echo "\n== variante 2: nome ==\n";
$r = $bling->get('/produtos', ['nome' => 'Letícia', 'limite' => 5]);
foreach ($r['data'] ?? [] as $p) echo "  {$p['codigo']} | {$p['nome']}\n";

echo "\n== variante 3: codigo ==\n";
$r = $bling->get('/produtos', ['codigo' => '75793', 'limite' => 5]);
foreach ($r['data'] ?? [] as $p) echo "  {$p['codigo']} | {$p['nome']}\n";

echo "\n== variante 4: pagina+limite sem outros ==\n";
$r = $bling->get('/produtos', ['pagina' => 1, 'limite' => 3]);
foreach ($r['data'] ?? [] as $p) echo "  {$p['codigo']} | {$p['nome']}\n";

echo "\n== sem variacoes (so pais) ==\n";
$r = $bling->get('/produtos', ['pesquisa' => 'Letícia', 'limite' => 10, 'tipo' => 'P']);
foreach ($r['data'] ?? [] as $p) echo "  {$p['codigo']} | {$p['nome']} | formato={$p['formato']}\n";

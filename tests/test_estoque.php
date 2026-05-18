<?php

require __DIR__ . '/../src/bootstrap.php';

use Amro\Integration\BlingClient;
use Amro\Service\ProdutoCatalogService;
use Amro\Tool\VerificarEstoqueTool;

$bling = new BlingClient(app_db(), $_ENV['BLING_CLIENT_ID'], $_ENV['BLING_CLIENT_SECRET']);
$catalog = new ProdutoCatalogService($bling, __DIR__ . '/../storage/produtos_cache.json');
$tool = new VerificarEstoqueTool($bling, $catalog);

echo "== Aquecendo cache (1ª chamada) ==\n";
$start = microtime(true);
$total = count($catalog->getCatalog());
echo "Cache populado: {$total} produtos pais em " . round(microtime(true) - $start, 2) . "s\n\n";

$casos = [
    ['nome_produto' => 'Scrubs Letícia',     'cor' => 'Preto',      'tamanho' => 'M'],
    ['nome_produto' => 'Scrubs Ysa Clássico', 'cor' => 'Azul Marinho', 'tamanho' => 'GG'],
    ['nome_produto' => 'Blusa Ysa Bazar',    'cor' => 'Azul Claro', 'tamanho' => 'G'],
    ['nome_produto' => 'Scrubs Letícia',     'cor' => 'Cinza',      'tamanho' => 'GG'],
    ['nome_produto' => 'Toucas',             'cor' => '',           'tamanho' => ''],
    ['nome_produto' => 'Produto Inexistente Bla Bla', 'cor' => '',  'tamanho' => ''],
];

foreach ($casos as $c) {
    echo "== nome='{$c['nome_produto']}' cor='{$c['cor']}' tam='{$c['tamanho']}' ==\n";
    $r = $tool->execute($c);
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
}

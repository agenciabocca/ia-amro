<?php

require __DIR__ . '/../src/bootstrap.php';

use Amro\Integration\BlingClient;
use Amro\Integration\MelhorEnvioClient;
use Amro\Integration\WoocommerceClient;
use Amro\Service\PrazoCalculatorService;
use Amro\Tool\CalcularPrazoEnvioTool;
use Amro\Tool\ConsultarPedidoStatusTool;
use Amro\Tool\ConsultarRastreioTool;
use Amro\Tool\EscalarParaHumanoTool;
use Amro\Tool\IdentificarClienteTool;
use Amro\Tool\VerificarEstoqueTool;

$db = app_db();
$bling = new BlingClient($db, $_ENV['BLING_CLIENT_ID'], $_ENV['BLING_CLIENT_SECRET']);
$me    = new MelhorEnvioClient($_ENV['ME_BASE_URL'], $_ENV['ME_ACCESS_TOKEN']);
$wc    = new WoocommerceClient($_ENV['WC_BASE_URL'], $_ENV['WC_CONSUMER_KEY'], $_ENV['WC_CONSUMER_SECRET']);
$prazo = new PrazoCalculatorService(
    (int) ($_ENV['PRAZO_PRONTA_ENTREGA_DIAS'] ?? 1),
    (int) ($_ENV['PRAZO_PRODUCAO_DIAS'] ?? 15)
);

function pretty(array $a): string
{
    return json_encode($a, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

echo "======================================================\n";
echo "T1) calcular_prazo_envio — pronta entrega hoje\n";
echo "======================================================\n";
$t = new CalcularPrazoEnvioTool($prazo);
echo pretty($t->execute(['data_pedido' => date('Y-m-d'), 'tem_producao' => false])) . "\n";

echo "\n======================================================\n";
echo "T2) calcular_prazo_envio — produção, pedido 30 dias atrás\n";
echo "======================================================\n";
echo pretty($t->execute(['data_pedido' => date('Y-m-d', strtotime('-30 days')), 'tem_producao' => true])) . "\n";

echo "\n======================================================\n";
echo "T3) consultar_pedido_status — pedido 17898 (Maria Graziela)\n";
echo "======================================================\n";
$t = new ConsultarPedidoStatusTool($bling, $prazo);
echo pretty($t->execute(['numero_pedido' => '17898'])) . "\n";

echo "\n======================================================\n";
echo "T4) consultar_rastreio — Anna Luyza (status=posted)\n";
echo "======================================================\n";
$t = new ConsultarRastreioTool($me, $prazo);
echo pretty($t->execute(['nome_cliente' => 'Anna Luyza', 'data_pedido' => '2026-05-13', 'tem_producao' => true])) . "\n";

echo "\n======================================================\n";
echo "T5) consultar_rastreio — Klarissa Medeiros (delivered)\n";
echo "======================================================\n";
echo pretty($t->execute(['nome_cliente' => 'Klarissa Medeiros'])) . "\n";

echo "\n======================================================\n";
echo "T6) consultar_rastreio — nome inexistente\n";
echo "======================================================\n";
echo pretty($t->execute(['nome_cliente' => 'Pedro Pedrinho Zzz', 'data_pedido' => date('Y-m-d', strtotime('-3 days')), 'tem_producao' => true])) . "\n";

echo "\n======================================================\n";
echo "T7) verificar_estoque — Scrubs Letícia Preto M\n";
echo "======================================================\n";
$t = new VerificarEstoqueTool($bling);
echo pretty($t->execute(['nome_produto' => 'Scrubs Letícia', 'cor' => 'Preto', 'tamanho' => 'M'])) . "\n";

echo "\n======================================================\n";
echo "T8) verificar_estoque — Blusa Ysa Bazar Azul Claro G (não existe)\n";
echo "======================================================\n";
echo pretty($t->execute(['nome_produto' => 'Blusa Ysa Bazar', 'cor' => 'Azul Claro', 'tamanho' => 'G'])) . "\n";

echo "\n======================================================\n";
echo "T9) identificar_cliente — por nome\n";
echo "======================================================\n";
$t = new IdentificarClienteTool($bling, $wc);
echo pretty($t->execute(['nome' => 'Maria Graziela'])) . "\n";

echo "\n======================================================\n";
echo "T10) escalar_para_humano — troca/devolução\n";
echo "======================================================\n";
$t = new EscalarParaHumanoTool($db);
echo pretty($t->execute([
    'phone'  => '5585999998888',
    'motivo' => 'troca_devolucao',
    'resumo' => 'Cliente quer trocar Scrubs Letícia M por tamanho G.',
])) . "\n";

echo "\n=== conversations table check ===\n";
$rows = $db->query("SELECT phone, ia_paused, last_message_at FROM conversations WHERE phone='5585999998888'")->fetchAll();
echo pretty($rows) . "\n";

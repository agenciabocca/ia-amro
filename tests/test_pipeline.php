<?php

require __DIR__ . '/../src/bootstrap.php';

use Amro\AppFactory;

$db = app_db();
$svc = AppFactory::conversationService($db);

$cenarios = [
    'A) cumprimento simples' => [
        'phone' => '5585991110001',
        'turnos' => ['Oi, tudo bem?'],
    ],
    'B) consulta pedido por número' => [
        'phone' => '5585991110002',
        'turnos' => ['Bom dia, quero saber o status do pedido 17898'],
    ],
    'C) cadê meu pedido (cliente conhecido com pedido entregue)' => [
        'phone' => '5585991110003',
        'turnos' => [
            'Oi',
            'Meu nome é Klarissa Medeiros, comprei um Scrubs, qual o status?',
        ],
    ],
    'D) rastreio pedido em produção (não postado ainda)' => [
        'phone' => '5585991110004',
        'turnos' => [
            'comprei na semana passada e quero código de rastreio. nome Maria Graziela',
        ],
    ],
    'E) estoque' => [
        'phone' => '5585991110005',
        'turnos' => ['Vocês têm Scrubs Letícia preto tamanho M?'],
    ],
    'F) troca → escala humano' => [
        'phone' => '5585991110006',
        'turnos' => ['oi, queria trocar o tamanho do meu Scrubs'],
    ],
];

foreach ($cenarios as $titulo => $c) {
    echo "==========================================\n";
    echo " {$titulo}\n";
    echo " phone={$c['phone']}\n";
    echo "==========================================\n";

    $db->prepare('DELETE FROM chat_memory WHERE session_id = ?')->execute([$c['phone']]);
    $db->prepare('DELETE FROM conversation_logs WHERE phone = ?')->execute([$c['phone']]);
    $db->prepare('UPDATE conversations SET ia_paused = 0 WHERE phone = ?')->execute([$c['phone']]);

    foreach ($c['turnos'] as $msg) {
        echo "\nVocê > {$msg}\n";
        $t0 = microtime(true);
        try {
            $r = $svc->handleIncoming($c['phone'], $msg);
        } catch (Throwable $e) {
            echo "  ERRO: " . $e->getMessage() . "\n";
            continue 2;
        }
        $reply = trim((string) ($r['reply'] ?? ''));
        $reply = preg_replace('/\s*\[(STATUS_RESPONDIDO|RASTREIO_ENTREGUE|ESTOQUE_RESPONDIDO|PASSAR_HUMANO|PRAZO_RESPONDIDO)\]\s*/u', '', $reply);
        echo "Ana > " . $reply . "\n";

        $tools = array_map(fn($t) => $t['tool'], $r['tool_calls'] ?? []);
        if ($tools) echo "       [tools: " . implode(', ', $tools) . "]\n";
        if (!empty($r['action'])) echo "       [action: {$r['action']}]\n";
        echo "       [latência: " . ($r['latency_ms'] ?? '?') . "ms | tokens: " . ($r['usage']['total_tokens'] ?? '?') . "]\n";
        if ($r['paused'] ?? false) {
            echo "       [escalada — IA pausada]\n";
        }
    }
    echo "\n";
}

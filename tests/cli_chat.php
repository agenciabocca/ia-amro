<?php

require __DIR__ . '/../src/bootstrap.php';

use Amro\AppFactory;

if (PHP_SAPI !== 'cli') {
    exit("Use no terminal: php tests/cli_chat.php [phone]\n");
}

$phone = $argv[1] ?? '5585999990001';

echo "==========================================\n";
echo " AMRO IA Suporte — CLI Chat Simulator\n";
echo "==========================================\n";
echo " Phone: {$phone}\n";
echo " Modelo: " . ($_ENV['OPENAI_MODEL'] ?? '?') . "\n";
echo " Comandos:\n";
echo "   /reset     - apaga histórico desse phone\n";
echo "   /resume    - reativa IA (caso tenha sido escalada)\n";
echo "   /history   - mostra histórico do DB\n";
echo "   /q         - sair\n";
echo "==========================================\n\n";

$db = app_db();
$svc = AppFactory::conversationService($db);

while (true) {
    echo "Você > ";
    $line = trim(fgets(STDIN));

    if ($line === '' || $line === '/q' || $line === 'exit' || $line === 'quit') {
        echo "tchau ☺️\n";
        break;
    }

    if ($line === '/reset') {
        $db->prepare('DELETE FROM chat_memory WHERE session_id = ?')->execute([$phone]);
        $db->prepare('DELETE FROM conversation_logs WHERE phone = ?')->execute([$phone]);
        $db->prepare('UPDATE conversations SET ia_paused = 0 WHERE phone = ?')->execute([$phone]);
        echo "  (histórico apagado)\n\n";
        continue;
    }

    if ($line === '/resume') {
        $db->prepare('UPDATE conversations SET ia_paused = 0 WHERE phone = ?')->execute([$phone]);
        echo "  (IA reativada)\n\n";
        continue;
    }

    if ($line === '/history') {
        $h = $db->prepare('SELECT role, LEFT(content, 200) AS preview FROM chat_memory WHERE session_id = ? ORDER BY id');
        $h->execute([$phone]);
        foreach ($h->fetchAll() as $row) {
            echo "  [{$row['role']}] {$row['preview']}\n";
        }
        echo "\n";
        continue;
    }

    $t0 = microtime(true);
    try {
        $r = $svc->handleIncoming($phone, $line);
    } catch (Throwable $e) {
        echo "  ERRO: " . $e->getMessage() . "\n\n";
        continue;
    }

    if ($r['paused'] ?? false) {
        echo "Ana > (IA pausada — atendimento humano. Use /resume pra reativar)\n\n";
        continue;
    }

    $reply = $r['reply'] ?? '(sem resposta)';
    $reply = preg_replace('/\s*\[(STATUS_RESPONDIDO|RASTREIO_ENTREGUE|ESTOQUE_RESPONDIDO|PASSAR_HUMANO|PRAZO_RESPONDIDO)\]\s*/u', '', $reply);
    echo "Ana > " . trim($reply) . "\n";

    if (!empty($r['tool_calls'])) {
        $tools = array_map(fn($t) => $t['tool'], $r['tool_calls']);
        echo "       [tools: " . implode(', ', $tools) . "]\n";
    }
    if (!empty($r['action'])) {
        echo "       [action: {$r['action']}]\n";
    }
    $latency = $r['latency_ms'] ?? 0;
    $tokens = $r['usage']['total_tokens'] ?? 0;
    echo "       [latência: {$latency}ms | tokens: {$tokens}]\n\n";
}

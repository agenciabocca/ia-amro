<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Amro\AppFactory;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

header('Content-Type: application/json; charset=utf-8');

try {
    switch ($path) {
        case '/':
        case '/health':
            echo json_encode([
                'status'    => 'ok',
                'app'       => 'amro-ia-suporte',
                'env'       => $_ENV['APP_ENV'] ?? '?',
                'timestamp' => date('c'),
                'php'       => PHP_VERSION,
            ]);
            break;

        case '/ping-db':
            $db = app_db();
            $row = $db->query('SELECT NOW() AS now, VERSION() AS ver')->fetch();
            echo json_encode(['ok' => true, 'db' => $row]);
            break;

        case '/test/chat':
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'POST only']);
                break;
            }

            $secret = $_ENV['TEST_ENDPOINT_SECRET'] ?? '';
            $given = $_SERVER['HTTP_X_TEST_SECRET'] ?? '';
            if ($secret === '' || $given !== $secret) {
                http_response_code(401);
                echo json_encode(['error' => 'unauthorized']);
                break;
            }

            $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
            $phone = (string) ($body['phone'] ?? '');
            $text  = (string) ($body['text'] ?? '');
            if ($phone === '' || $text === '') {
                http_response_code(400);
                echo json_encode(['error' => 'phone and text required']);
                break;
            }

            $svc = AppFactory::conversationService(app_db());
            $r = $svc->handleIncoming($phone, $text);
            echo json_encode($r, JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'not found', 'path' => $path]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    app_log('public error: ' . $e->getMessage(), 'error', [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
    echo json_encode([
        'error' => $debug ? $e->getMessage() : 'internal error',
        'file'  => $debug ? $e->getFile() . ':' . $e->getLine() : null,
    ]);
}

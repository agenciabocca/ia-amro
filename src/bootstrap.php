<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo');

if (filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/php_error.log');
}

function app_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $cfg = require __DIR__ . '/../config/database.php';
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $cfg['options']);
    }
    return $pdo;
}

function app_log(string $message, string $level = 'info', array $context = []): void
{
    $line = sprintf(
        "[%s] [%s] %s%s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : ''
    );
    @file_put_contents(__DIR__ . '/../logs/app.log', $line, FILE_APPEND);
}

function env_bool(string $key, bool $default = false): bool
{
    if (!isset($_ENV[$key])) {
        return $default;
    }
    return filter_var($_ENV[$key], FILTER_VALIDATE_BOOLEAN);
}

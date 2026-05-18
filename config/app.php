<?php

return [
    'url'      => $_ENV['APP_URL'] ?? 'http://localhost',
    'env'      => $_ENV['APP_ENV'] ?? 'production',
    'debug'    => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo',
    'prazos'   => [
        'pronta_entrega_dias' => (int) ($_ENV['PRAZO_PRONTA_ENTREGA_DIAS'] ?? 1),
        'producao_dias'       => (int) ($_ENV['PRAZO_PRODUCAO_DIAS'] ?? 15),
    ],
];

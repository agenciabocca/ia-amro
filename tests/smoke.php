<?php
require __DIR__ . '/../src/bootstrap.php';
echo "APP_ENV: " . ($_ENV['APP_ENV'] ?? 'unset') . "\n";
echo "OPENAI_API_KEY length: " . strlen($_ENV['OPENAI_API_KEY'] ?? '') . "\n";
echo "BLING_CLIENT_ID set: " . (!empty($_ENV['BLING_CLIENT_ID']) ? 'yes' : 'no') . "\n";
echo "ME_ACCESS_TOKEN length: " . strlen($_ENV['ME_ACCESS_TOKEN'] ?? '') . "\n";
echo "WC_CONSUMER_KEY set: " . (!empty($_ENV['WC_CONSUMER_KEY']) ? 'yes' : 'no') . "\n";
echo "BlingClient class: " . (class_exists('Amro\\Integration\\BlingClient') ? 'ok' : 'MISSING') . "\n";
echo "MelhorEnvioClient class: " . (class_exists('Amro\\Integration\\MelhorEnvioClient') ? 'ok' : 'MISSING') . "\n";
echo "OpenAIClient class: " . (class_exists('Amro\\Integration\\OpenAIClient') ? 'ok' : 'MISSING') . "\n";
echo "WoocommerceClient class: " . (class_exists('Amro\\Integration\\WoocommerceClient') ? 'ok' : 'MISSING') . "\n";

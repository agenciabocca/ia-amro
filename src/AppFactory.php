<?php

declare(strict_types=1);

namespace Amro;

use Amro\Integration\BlingClient;
use Amro\Integration\MelhorEnvioClient;
use Amro\Integration\OpenAIClient;
use Amro\Integration\WoocommerceClient;
use Amro\Prompt\PromptBuilder;
use Amro\Service\AIAgentService;
use Amro\Service\ConversationService;
use Amro\Service\PrazoCalculatorService;
use Amro\Service\ProdutoCatalogService;
use Amro\Service\RateLimiterService;
use Amro\Tool\CalcularPrazoEnvioTool;
use Amro\Tool\ConsultarPedidoStatusTool;
use Amro\Tool\ConsultarRastreioTool;
use Amro\Tool\EscalarParaHumanoTool;
use Amro\Tool\IdentificarClienteTool;
use Amro\Tool\ToolRegistry;
use Amro\Tool\VerificarEstoqueTool;
use PDO;

class AppFactory
{
    public static function conversationService(PDO $db): ConversationService
    {
        $bling = new BlingClient($db, $_ENV['BLING_CLIENT_ID'], $_ENV['BLING_CLIENT_SECRET']);
        $me    = new MelhorEnvioClient($_ENV['ME_BASE_URL'], $_ENV['ME_ACCESS_TOKEN']);
        $wc    = new WoocommerceClient($_ENV['WC_BASE_URL'], $_ENV['WC_CONSUMER_KEY'], $_ENV['WC_CONSUMER_SECRET']);
        $ai    = new OpenAIClient($_ENV['OPENAI_API_KEY'], $_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini');

        $prazo = new PrazoCalculatorService(
            (int) ($_ENV['PRAZO_PRONTA_ENTREGA_DIAS'] ?? 1),
            (int) ($_ENV['PRAZO_PRODUCAO_DIAS'] ?? 15)
        );

        $catalog = new ProdutoCatalogService(
            $bling,
            __DIR__ . '/../storage/produtos_cache.json'
        );

        $registry = new ToolRegistry();
        $registry->register(new IdentificarClienteTool($bling, $wc));
        $registry->register(new ConsultarPedidoStatusTool($bling, $prazo));
        $registry->register(new ConsultarRastreioTool($me, $prazo));
        $registry->register(new VerificarEstoqueTool($bling, $catalog));
        $registry->register(new CalcularPrazoEnvioTool($prazo));
        $registry->register(new EscalarParaHumanoTool($db));

        $agent = new AIAgentService(
            $ai,
            $registry,
            (int) ($_ENV['AI_MAX_ITERATIONS'] ?? 3)
        );

        $promptBuilder = new PromptBuilder($db);

        $rateLimiter = new RateLimiterService(
            $db,
            (int) ($_ENV['RATE_LIMIT_MAX_MESSAGES'] ?? 12),
            (int) ($_ENV['RATE_LIMIT_WINDOW_SECONDS'] ?? 60)
        );

        return new ConversationService($db, $promptBuilder, $agent, $rateLimiter);
    }
}

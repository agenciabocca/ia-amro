<?php

declare(strict_types=1);

namespace Amro\Tool;

use Amro\Integration\MelhorEnvioClient;
use Amro\Service\PrazoCalculatorService;

class ConsultarRastreioTool implements ToolInterface
{
    private const STATUS_DESC = [
        'pending'   => 'em separação/produção (etiqueta ainda não gerada)',
        'released'  => 'etiqueta gerada, aguardando postagem',
        'posted'    => 'postado e em trânsito',
        'delivered' => 'entregue',
        'canceled'  => 'cancelado',
        'undelivered' => 'tentativa de entrega sem sucesso',
        'returned'  => 'devolvido',
    ];

    public function __construct(
        private MelhorEnvioClient $me,
        private PrazoCalculatorService $prazo,
    ) {}

    public function getName(): string
    {
        return 'consultar_rastreio';
    }

    public function getDefinition(): array
    {
        return [
            'name'        => $this->getName(),
            'description' => 'Consulta status de envio + código de rastreio no Melhor Envio (fonte de verdade). Busca POR NOME do destinatário. IMPORTANTE: se o cliente só te passou número de pedido, chame consultar_pedido_status PRIMEIRO pra obter o cliente_nome — depois passe esse nome aqui.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'nome_cliente' => [
                        'type'        => 'string',
                        'description' => 'Nome PESSOAL do cliente (ex: "Maria Graziela"). Pode ser parcial. NUNCA passe número de pedido aqui — esse campo só aceita nome de pessoa.',
                    ],
                    'data_pedido' => [
                        'type'        => 'string',
                        'description' => 'Data do pedido no formato YYYY-MM-DD (vinda do Bling) — para calcular prazo previsto se ainda não foi postado.',
                    ],
                    'tem_producao' => [
                        'type'        => 'boolean',
                        'description' => 'true se pedido contém peça de produção (15 dias úteis), false se só pronta entrega (1 dia útil). Default true (mais conservador).',
                    ],
                ],
                'required' => ['nome_cliente'],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $nome = trim((string) ($input['nome_cliente'] ?? ''));
        $dataPedido = trim((string) ($input['data_pedido'] ?? ''));
        $temProducao = (bool) ($input['tem_producao'] ?? true);

        if ($nome === '') {
            return ['error' => 'Informe o nome do cliente.'];
        }

        if (preg_match('/^\d+$/', $nome)) {
            return [
                'error' => 'O argumento nome_cliente deve ser nome de pessoa, não número de pedido. Use consultar_pedido_status({numero_pedido}) primeiro pra obter o cliente_nome — depois passe esse nome aqui.',
            ];
        }

        try {
            $r = $this->me->getOrders(['q' => $nome], 1, 10);
        } catch (\Throwable $e) {
            return ['error' => 'Falha ao consultar Melhor Envio: ' . $e->getMessage()];
        }

        $pedidos = $r['data'] ?? [];
        if (empty($pedidos)) {
            $resp = [
                'encontrado' => false,
                'mensagem'   => "Não encontrei envio no Melhor Envio pra '{$nome}'. Pode ser que o pedido ainda esteja em produção (sem etiqueta gerada) OU o nome não bate exatamente. Confirme o nome cadastrado.",
            ];
            if ($dataPedido) {
                $resp['prazo_previsto'] = $this->prazo->calcular($dataPedido, $temProducao);
            }
            return $resp;
        }

        usort($pedidos, fn($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        $pedido = $pedidos[0];

        $status = $pedido['status'] ?? 'unknown';
        $tracking = $pedido['tracking'] ?? null;
        $postedAt = $pedido['posted_at'] ?? null;
        $deliveredAt = $pedido['delivered_at'] ?? null;
        $generatedAt = $pedido['generated_at'] ?? null;

        $resp = [
            'encontrado'        => true,
            'status'            => $status,
            'status_descricao'  => self::STATUS_DESC[$status] ?? $status,
            'codigo_rastreio'   => $tracking,
            'transportadora'    => $pedido['service']['name'] ?? null,
            'data_criacao'      => $pedido['created_at'] ?? null,
            'data_geracao_etiqueta' => $generatedAt,
            'data_postagem'     => $postedAt,
            'data_entrega'      => $deliveredAt,
            'destinatario_nome' => $pedido['to']['name'] ?? null,
            'destinatario_cep'  => $pedido['to']['postal_code'] ?? null,
            'me_protocol'       => $pedido['protocol'] ?? null,
            'pedidos_outros_count' => max(0, count($pedidos) - 1),
        ];

        if (in_array($status, ['pending', 'released'], true) && $dataPedido) {
            $resp['prazo_previsto'] = $this->prazo->calcular($dataPedido, $temProducao);
        }

        return $resp;
    }
}

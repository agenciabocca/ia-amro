<?php

declare(strict_types=1);

namespace Amro\Tool;

use Amro\Integration\BlingClient;
use Amro\Service\PrazoCalculatorService;

class ConsultarPedidoStatusTool implements ToolInterface
{
    private const SITUACAO_LABEL = [
        6  => 'em_aberto',
        9  => 'atendido',
        12 => 'cancelado',
        15 => 'em_andamento',
        21 => 'preparando_envio',
        24 => 'verificado',
    ];

    private const CATEGORIA_PRONTA_ENTREGA_ID = 13297007;

    public function __construct(
        private BlingClient $bling,
        private PrazoCalculatorService $prazo,
    ) {}

    public function getName(): string
    {
        return 'consultar_pedido_status';
    }

    public function getDefinition(): array
    {
        return [
            'name'        => $this->getName(),
            'description' => 'Retorna situação detalhada de um pedido AMRO: data, itens, se já foi enviado, prazo previsto. Você pode buscar por número do pedido OU pelo nome do cliente.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'numero_pedido' => [
                        'type'        => 'string',
                        'description' => 'Número do pedido no Bling (ex: 17898).',
                    ],
                    'nome_cliente' => [
                        'type'        => 'string',
                        'description' => 'Nome do cliente — usado se não tiver o número. Retorna o pedido mais recente desse nome.',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $numero = trim((string) ($input['numero_pedido'] ?? ''));
        $nome = trim((string) ($input['nome_cliente'] ?? ''));

        $pedido = null;

        if ($numero !== '') {
            try {
                $r = $this->bling->get('/pedidos/vendas', ['numero' => $numero, 'limite' => 1]);
                $pedido = $r['data'][0] ?? null;
            } catch (\Throwable $e) {
                return ['error' => 'Falha ao buscar pedido: ' . $e->getMessage()];
            }
        } elseif ($nome !== '') {
            try {
                $r = $this->bling->get('/pedidos/vendas', ['idsContatos' => '', 'limite' => 5, 'ordem' => 'desc']);
                foreach ($r['data'] ?? [] as $p) {
                    if (stripos($p['contato']['nome'] ?? '', $nome) !== false) {
                        $pedido = $p;
                        break;
                    }
                }
                if (!$pedido) {
                    $r = $this->bling->get('/pedidos/vendas', ['limite' => 50, 'ordem' => 'desc']);
                    foreach ($r['data'] ?? [] as $p) {
                        if (stripos($p['contato']['nome'] ?? '', $nome) !== false) {
                            $pedido = $p;
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                return ['error' => 'Falha ao buscar pedido: ' . $e->getMessage()];
            }
        } else {
            return ['error' => 'Informe número do pedido ou nome do cliente.'];
        }

        if (!$pedido) {
            return [
                'encontrado' => false,
                'mensagem'   => 'Pedido não localizado. Confirme o número OU peça o nome cadastrado.',
            ];
        }

        $det = $this->bling->get('/pedidos/vendas/' . $pedido['id']);
        $data = $det['data'] ?? [];

        $situacaoId = (int) ($data['situacao']['id'] ?? 0);
        $situacaoLabel = self::SITUACAO_LABEL[$situacaoId] ?? "situacao_{$situacaoId}";

        $temProducao = $this->temItemProducao($data['itens'] ?? []);
        $prazo = $this->prazo->calcular($data['data'] ?? date('Y-m-d'), $temProducao);

        $codigoRastreio = $this->extrairCodigoRastreio($data['transporte'] ?? []);

        return [
            'encontrado'     => true,
            'pedido_numero'  => $data['numero'] ?? null,
            'cliente_nome'   => $data['contato']['nome'] ?? null,
            'data_pedido'    => $data['data'] ?? null,
            'data_saida'     => $data['dataSaida'] ?: null,
            'situacao'       => $situacaoLabel,
            'situacao_id'    => $situacaoId,
            'total'          => $data['total'] ?? null,
            'itens' => array_map(
                fn($i) => [
                    'sku'        => $i['codigo'] ?? null,
                    'descricao'  => $i['descricao'] ?? null,
                    'quantidade' => $i['quantidade'] ?? null,
                ],
                array_slice($data['itens'] ?? [], 0, 10)
            ),
            'tipo_pedido'    => $temProducao ? 'producao' : 'pronta_entrega',
            'prazo'          => $prazo,
            'codigo_rastreio_bling' => $codigoRastreio,
            'ja_enviado'     => $situacaoId === 9 || $situacaoId === 24 || $codigoRastreio !== null,
        ];
    }

    private function temItemProducao(array $itens): bool
    {
        if (empty($itens)) {
            return true;
        }
        foreach ($itens as $i) {
            $desc = $i['descricao'] ?? '';
            $codigo = $i['codigo'] ?? '';
            if (
                stripos($desc, 'PRONTA ENTREGA') === false &&
                stripos($desc, 'Pronta entrega') === false &&
                stripos($codigo, 'ADDON-') !== 0
            ) {
                return true;
            }
        }
        return false;
    }

    private function extrairCodigoRastreio(array $transporte): ?string
    {
        foreach ($transporte['volumes'] ?? [] as $v) {
            if (!empty($v['codigoRastreamento'])) {
                return $v['codigoRastreamento'];
            }
        }
        return null;
    }
}

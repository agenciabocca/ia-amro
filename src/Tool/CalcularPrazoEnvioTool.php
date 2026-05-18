<?php

declare(strict_types=1);

namespace Amro\Tool;

use Amro\Service\PrazoCalculatorService;

class CalcularPrazoEnvioTool implements ToolInterface
{
    public function __construct(private PrazoCalculatorService $prazo) {}

    public function getName(): string
    {
        return 'calcular_prazo_envio';
    }

    public function getDefinition(): array
    {
        return [
            'name'        => $this->getName(),
            'description' => 'Calcula a data prevista de despacho de um pedido baseado na regra AMRO: PRONTA ENTREGA = 1 dia útil, peça de produção = 15 dias úteis. Considera só dias úteis (skip sábado/domingo).',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'data_pedido'  => [
                        'type'        => 'string',
                        'description' => 'Data do pedido (YYYY-MM-DD).',
                    ],
                    'tem_producao' => [
                        'type'        => 'boolean',
                        'description' => 'true se inclui peça de produção, false se só pronta entrega.',
                    ],
                ],
                'required' => ['data_pedido', 'tem_producao'],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $data = trim((string) ($input['data_pedido'] ?? ''));
        if ($data === '') {
            return ['error' => 'Informe data_pedido.'];
        }
        $temProducao = (bool) ($input['tem_producao'] ?? false);
        return $this->prazo->calcular($data, $temProducao);
    }
}

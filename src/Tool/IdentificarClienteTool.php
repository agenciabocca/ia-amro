<?php

declare(strict_types=1);

namespace Amro\Tool;

use Amro\Integration\BlingClient;
use Amro\Integration\WoocommerceClient;

class IdentificarClienteTool implements ToolInterface
{
    public function __construct(
        private BlingClient $bling,
        private WoocommerceClient $wc,
    ) {}

    public function getName(): string
    {
        return 'identificar_cliente';
    }

    public function getDefinition(): array
    {
        return [
            'name'        => $this->getName(),
            'description' => 'Localiza um cliente AMRO pelo nome ou telefone e retorna pedidos recentes. Use quando precisar saber quem é o cliente antes de consultar pedido/rastreio.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'nome'  => [
                        'type'        => 'string',
                        'description' => 'Nome completo ou parcial do cliente (ex: "Maria Graziela").',
                    ],
                    'phone' => [
                        'type'        => 'string',
                        'description' => 'Telefone do cliente, com DDD, só dígitos (ex: "5585999998888").',
                    ],
                ],
                'required' => [],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $nome = trim((string) ($input['nome'] ?? ''));
        $phone = preg_replace('/\D/', '', (string) ($input['phone'] ?? ''));

        if ($nome === '' && $phone === '') {
            return ['error' => 'Informe nome ou telefone do cliente.'];
        }

        $candidatos = [];

        if ($phone !== '') {
            try {
                $wcResp = $this->wc->get('/customers', ['search' => $phone, 'per_page' => 5]);
                foreach ($wcResp as $c) {
                    $candidatos[] = [
                        'fonte'   => 'wc',
                        'id'      => $c['id'],
                        'nome'    => trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')),
                        'email'   => $c['email'] ?? null,
                        'phone'   => $c['billing']['phone'] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                // segue sem WC
            }
        }

        if ($nome !== '') {
            try {
                $blingResp = $this->bling->get('/contatos', ['pesquisa' => $nome, 'limite' => 5]);
                foreach ($blingResp['data'] ?? [] as $c) {
                    $candidatos[] = [
                        'fonte' => 'bling',
                        'id'    => $c['id'],
                        'nome'  => $c['nome'] ?? '',
                        'documento' => $c['numeroDocumento'] ?? null,
                        'tipo'  => $c['tipo'] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                // segue sem Bling
            }
        }

        if (empty($candidatos)) {
            return [
                'encontrado' => false,
                'mensagem'   => 'Cliente não localizado. Peça o número do pedido OU confirme o nome completo cadastrado.',
            ];
        }

        return [
            'encontrado' => true,
            'candidatos' => array_slice($candidatos, 0, 5),
        ];
    }
}

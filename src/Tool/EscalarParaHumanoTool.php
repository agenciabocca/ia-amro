<?php

declare(strict_types=1);

namespace Amro\Tool;

use PDO;

class EscalarParaHumanoTool implements ToolInterface
{
    public function __construct(private PDO $db) {}

    public function getName(): string
    {
        return 'escalar_para_humano';
    }

    public function getDefinition(): array
    {
        return [
            'name'        => $this->getName(),
            'description' => 'Encerra atendimento da IA e passa pra uma vendedora humana. Use em: agressão verbal, troca/devolução, problema de cobrança/cartão, pedido sem cliente identificado depois de 2 tentativas, pergunta fora do escopo (pré-venda complexa, indicação tamanho, etc.).',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'phone'   => [
                        'type'        => 'string',
                        'description' => 'Telefone do cliente (do contexto da conversa).',
                    ],
                    'motivo'  => [
                        'type'        => 'string',
                        'description' => 'Categoria curta: agressao, troca_devolucao, cobranca, fora_escopo, cliente_nao_identificado, outros.',
                    ],
                    'resumo'  => [
                        'type'        => 'string',
                        'description' => 'Resumo de 1-3 frases do que cliente quer, pra vendedora não precisar reler tudo.',
                    ],
                ],
                'required' => ['phone', 'motivo'],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $phone = preg_replace('/\D/', '', (string) ($input['phone'] ?? ''));
        $motivo = trim((string) ($input['motivo'] ?? 'outros'));
        $resumo = trim((string) ($input['resumo'] ?? ''));

        if ($phone === '') {
            return ['error' => 'Informe phone do cliente.'];
        }

        $sql = 'INSERT INTO conversations (phone, ia_paused, last_message_at)
                VALUES (?, 1, NOW())
                ON DUPLICATE KEY UPDATE ia_paused = 1, last_message_at = NOW()';
        $this->db->prepare($sql)->execute([$phone]);

        $this->db->prepare(
            'INSERT INTO conversation_logs (phone, direction, message, ai_action, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        )->execute([$phone, 'system', "ESCALATION motivo={$motivo} resumo={$resumo}", '[PASSAR_HUMANO]']);

        return [
            'escalated' => true,
            'motivo'    => $motivo,
            'mensagem_para_cliente' => 'Vou pedir pra uma colega continuar com você — ela já viu nosso histórico aqui ☺️',
        ];
    }
}

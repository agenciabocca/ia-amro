<?php

declare(strict_types=1);

namespace Amro\Service;

use DateTime;
use DateTimeImmutable;

class PrazoCalculatorService
{
    private int $prazoProntaEntregaDias;
    private int $prazoProducaoDias;

    public function __construct(int $prazoProntaEntregaDias = 1, int $prazoProducaoDias = 15)
    {
        $this->prazoProntaEntregaDias = $prazoProntaEntregaDias;
        $this->prazoProducaoDias = $prazoProducaoDias;
    }

    public function calcular(string $dataPedido, bool $temProducao): array
    {
        $diasUteis = $temProducao ? $this->prazoProducaoDias : $this->prazoProntaEntregaDias;
        $inicio = new DateTimeImmutable($dataPedido);
        $previsao = $this->somarDiasUteis($inicio, $diasUteis);
        $hoje = new DateTimeImmutable('today');

        $diasRestantes = $this->contarDiasUteis($hoje, $previsao);
        $dentroPrazo = $hoje <= $previsao;

        return [
            'data_pedido'    => $inicio->format('Y-m-d'),
            'data_prevista'  => $previsao->format('Y-m-d'),
            'tipo'           => $temProducao ? 'producao' : 'pronta_entrega',
            'prazo_dias_uteis' => $diasUteis,
            'dentro_prazo'   => $dentroPrazo,
            'dias_restantes' => $diasRestantes,
            'atrasado_dias_uteis' => $dentroPrazo ? 0 : $this->contarDiasUteis($previsao, $hoje),
        ];
    }

    public function somarDiasUteis(DateTimeImmutable $inicio, int $dias): DateTimeImmutable
    {
        $data = $inicio;
        $adicionados = 0;
        while ($adicionados < $dias) {
            $data = $data->modify('+1 day');
            if ($this->ehDiaUtil($data)) {
                $adicionados++;
            }
        }
        return $data;
    }

    public function contarDiasUteis(DateTimeImmutable $inicio, DateTimeImmutable $fim): int
    {
        if ($fim < $inicio) {
            [$inicio, $fim] = [$fim, $inicio];
        }
        $count = 0;
        $atual = $inicio;
        while ($atual < $fim) {
            $atual = $atual->modify('+1 day');
            if ($this->ehDiaUtil($atual)) {
                $count++;
            }
        }
        return $count;
    }

    private function ehDiaUtil(DateTimeImmutable $data): bool
    {
        $w = (int) $data->format('N');
        return $w >= 1 && $w <= 5;
    }
}

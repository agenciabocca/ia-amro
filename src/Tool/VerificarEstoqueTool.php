<?php

declare(strict_types=1);

namespace Amro\Tool;

use Amro\Integration\BlingClient;
use Amro\Service\ProdutoCatalogService;

class VerificarEstoqueTool implements ToolInterface
{
    public function __construct(
        private BlingClient $bling,
        private ProdutoCatalogService $catalog,
    ) {}

    public function getName(): string
    {
        return 'verificar_estoque';
    }

    public function getDefinition(): array
    {
        return [
            'name'        => $this->getName(),
            'description' => 'Verifica estoque de uma peça AMRO no Bling. Recebe nome do produto (ex: "Scrubs Ysa Clássico"), opcionalmente cor e tamanho. Retorna saldo de cada variação que casa.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'nome_produto' => [
                        'type'        => 'string',
                        'description' => 'Nome do produto. Quanto mais completo, melhor (ex: "Scrubs Ysa Clássico" ao invés de só "ysa").',
                    ],
                    'cor' => [
                        'type'        => 'string',
                        'description' => 'Cor desejada (ex: "Preto", "Azul Marinho"). Opcional.',
                    ],
                    'tamanho' => [
                        'type'        => 'string',
                        'description' => 'Tamanho (PP, P, M, G, GG, EG, EGG, EXG, EXGG). Opcional.',
                    ],
                ],
                'required' => ['nome_produto'],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $nome = trim((string) ($input['nome_produto'] ?? ''));
        $cor = trim((string) ($input['cor'] ?? ''));
        $tamanho = strtoupper(trim((string) ($input['tamanho'] ?? '')));

        if ($nome === '') {
            return ['error' => 'Informe o nome do produto.'];
        }

        $pais = $this->catalog->buscarPais($nome, 3);
        if (empty($pais)) {
            return [
                'encontrado' => false,
                'mensagem'   => "Não encontrei produto com nome próximo a '{$nome}'. Confirme o nome ou peça mais completo.",
            ];
        }

        $resultados = [];
        foreach ($pais as $pai) {
            try {
                $det = $this->bling->get('/produtos/' . $pai['id']);
            } catch (\Throwable $e) {
                continue;
            }
            $produto = $det['data'] ?? [];

            $variacoes = $produto['variacoes'] ?? [];
            if (empty($variacoes)) {
                $resultados[] = [
                    'produto_pai' => $produto['nome'] ?? $pai['nome'],
                    'sku'         => $produto['codigo'] ?? $pai['codigo'],
                    'nome'        => $produto['nome'] ?? $pai['nome'],
                    'saldo'       => $produto['estoque']['saldoVirtualTotal'] ?? null,
                ];
                continue;
            }

            $filtradas = $this->filtrarVariacoes($variacoes, $cor, $tamanho);
            foreach ($filtradas as $v) {
                $resultados[] = [
                    'produto_pai' => $produto['nome'] ?? $pai['nome'],
                    'sku'         => $v['codigo'] ?? null,
                    'nome'        => $v['nome'] ?? null,
                    'saldo'       => $v['estoque']['saldoVirtualTotal'] ?? null,
                ];
            }
        }

        if (empty($resultados)) {
            $filtros = trim("cor={$cor} tamanho={$tamanho}");
            return [
                'encontrado' => false,
                'mensagem'   => "Produto encontrado mas sem a combinação pedida ({$filtros}). Pode estar fora de cadastro no site.",
                'produto_pai_encontrado' => $pais[0]['nome'] ?? null,
            ];
        }

        return [
            'encontrado' => true,
            'resultados' => array_slice($resultados, 0, 15),
        ];
    }

    private function filtrarVariacoes(array $variacoes, string $cor, string $tamanho): array
    {
        if ($cor === '' && $tamanho === '') {
            return $variacoes;
        }
        $corNorm = $this->normalize($cor);
        $tamUp = strtoupper($tamanho);

        $resultado = [];
        foreach ($variacoes as $v) {
            $codigo = strtoupper($v['codigo'] ?? '');
            $nomeNorm = $this->normalize($v['nome'] ?? '');
            $okCor = $cor === '' || str_contains($nomeNorm, $corNorm)
                                 || str_contains($codigo, strtoupper(str_replace(' ', '-', $cor)));
            $okTam = $tamanho === '' || $this->tamanhoBate($codigo, (string) ($v['nome'] ?? ''), $tamUp);
            if ($okCor && $okTam) {
                $resultado[] = $v;
            }
        }
        return $resultado;
    }

    private function tamanhoBate(string $codigo, string $nome, string $tamanho): bool
    {
        if (preg_match('/-' . preg_quote($tamanho, '/') . '$/i', $codigo)) {
            return true;
        }
        if (preg_match('/Tamanho\s*:\s*' . preg_quote($tamanho, '/') . '\b/i', $nome)) {
            return true;
        }
        if (preg_match('/,\s*' . preg_quote($tamanho, '/') . '\s*$/i', $nome)) {
            return true;
        }
        return false;
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[áàâãä]/u', 'a', $s);
        $s = preg_replace('/[éèêë]/u', 'e', $s);
        $s = preg_replace('/[íìîï]/u', 'i', $s);
        $s = preg_replace('/[óòôõö]/u', 'o', $s);
        $s = preg_replace('/[úùûü]/u', 'u', $s);
        $s = preg_replace('/ç/u', 'c', $s);
        return $s;
    }
}

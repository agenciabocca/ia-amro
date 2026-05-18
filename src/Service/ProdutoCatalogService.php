<?php

declare(strict_types=1);

namespace Amro\Service;

use Amro\Integration\BlingClient;

class ProdutoCatalogService
{
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private BlingClient $bling,
        private string $cachePath,
    ) {}

    public function buscarPais(string $query, int $limit = 5): array
    {
        $catalog = $this->getCatalog();
        $query = $this->normalize($query);

        $scored = [];
        foreach ($catalog as $p) {
            $score = $this->score($this->normalize($p['nome'] ?? ''), $query);
            if ($score > 0) {
                $scored[] = ['produto' => $p, 'score' => $score];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice(array_map(fn($s) => $s['produto'], $scored), 0, $limit);
    }

    public function getCatalog(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && file_exists($this->cachePath)) {
            $age = time() - filemtime($this->cachePath);
            if ($age < self::CACHE_TTL_SECONDS) {
                $data = json_decode((string) file_get_contents($this->cachePath), true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }

        $todos = [];
        $page = 1;
        while ($page <= 30) {
            $r = $this->bling->get('/produtos', ['limite' => 100, 'pagina' => $page]);
            $batch = $r['data'] ?? [];
            if (empty($batch)) {
                break;
            }
            foreach ($batch as $p) {
                if (($p['formato'] ?? '') !== 'V') {
                    continue;
                }
                if (empty($p['codigo'])) {
                    continue;
                }
                $todos[] = [
                    'id'     => $p['id'],
                    'codigo' => $p['codigo'],
                    'nome'   => $p['nome'],
                ];
            }
            if (count($batch) < 100) {
                break;
            }
            $page++;
        }

        @file_put_contents($this->cachePath, json_encode($todos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $todos;
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
        $s = preg_replace('/[^a-z0-9 ]/', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private function score(string $produtoNome, string $query): float
    {
        if ($produtoNome === '' || $query === '') {
            return 0.0;
        }
        if (str_contains($produtoNome, $query)) {
            return 1.0;
        }
        $palavras = explode(' ', $query);
        $achadas = 0;
        foreach ($palavras as $w) {
            if (mb_strlen($w) >= 3 && str_contains($produtoNome, $w)) {
                $achadas++;
            }
        }
        if ($achadas === 0) {
            return 0.0;
        }
        return $achadas / max(count($palavras), 1);
    }
}

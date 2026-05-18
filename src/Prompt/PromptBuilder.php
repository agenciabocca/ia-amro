<?php

declare(strict_types=1);

namespace Amro\Prompt;

use PDO;

class PromptBuilder
{
    public function __construct(private PDO $db) {}

    public function build(string $key = 'prompt_amro_v1'): string
    {
        $row = $this->db->prepare('SELECT config_value FROM config WHERE config_key = ?');
        $row->execute([$key]);
        $value = $row->fetchColumn();
        if ($value) {
            return (string) $value;
        }

        $fallback = file_get_contents(__DIR__ . '/default.txt');
        if ($fallback) {
            $this->db->prepare('INSERT INTO config (config_key, config_value) VALUES (?, ?)')
                     ->execute([$key, $fallback]);
            return $fallback;
        }

        throw new \RuntimeException('System prompt não encontrado (DB nem default.txt).');
    }

    public function buildContext(string $phone, ?string $clienteNome = null): string
    {
        $hoje = date('Y-m-d (l)');
        $ctx = "# CONTEXTO DESTA CONVERSA\n";
        $ctx .= "- Data/hora agora: {$hoje}\n";
        $ctx .= "- Telefone do cliente: {$phone}\n";
        if ($clienteNome) {
            $ctx .= "- Nome do cliente (já identificado): {$clienteNome}\n";
        } else {
            $ctx .= "- Cliente ainda não identificado (peça o nome se precisar).\n";
        }
        return $ctx;
    }
}

<?php

declare(strict_types=1);

namespace Amro\Tool;

use RuntimeException;

class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /** @return array<int, array> definitions no formato OpenAI tools */
    public function getOpenAIDefinitions(): array
    {
        $defs = [];
        foreach ($this->tools as $tool) {
            $defs[] = [
                'type'     => 'function',
                'function' => $tool->getDefinition(),
            ];
        }
        return $defs;
    }

    public function execute(string $name, array $input): array
    {
        if (!isset($this->tools[$name])) {
            throw new RuntimeException("Tool '{$name}' não registrada");
        }
        return $this->tools[$name]->execute($input);
    }
}

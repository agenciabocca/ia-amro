<?php

declare(strict_types=1);

namespace Amro\Tool;

interface ToolInterface
{
    public function getName(): string;

    public function getDefinition(): array;

    public function execute(array $input): array;
}

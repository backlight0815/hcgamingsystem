<?php

namespace App\Services\Ai;

interface AiProviderInterface
{
    public function generateStructured(string $system, array $input, array $schema, int $maxTokens = 4000): array;

    public function model(): string;
}

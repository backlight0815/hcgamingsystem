<?php

namespace App\Services\Ai;

use InvalidArgumentException;

/** Module-neutral registry and provider dispatcher. */
final class AiGenerationService
{
    public function __construct(private readonly AiProviderInterface $provider)
    {
    }

    public function generate(string $consumer, string $system, array $context, array $schema, int $maxTokens = 4000): array
    {
        if ($consumer === '' || ! isset($schema['properties'])) {
        throw new InvalidArgumentException('Invalid AI consumer or schema.');
        }

        return $this->provider->generateStructured($system, ['consumer' => $consumer, 'source' => $context], $schema, $maxTokens);
    }

    public function model(): string
    {
    return $this->provider->model();
    }
}

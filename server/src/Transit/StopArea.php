<?php

declare(strict_types=1);

namespace App\Transit;

/**
 * Zone d'arrêt : tous les quais d'un arrêt, quelle que soit la direction.
 */
final readonly class StopArea
{
    /**
     * @param list<string> $lines lignes desservies
     */
    public function __construct(
        public string $ref,
        public string $name,
        public array $lines,
    ) {
    }

    public function isServedBy(string $line): bool
    {
        return \in_array($line, $this->lines, true);
    }
}

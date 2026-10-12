<?php

declare(strict_types=1);

namespace App\Chaban\Api;

/**
 * Réponse de l'API Explore (Opendatasoft) de Bordeaux Métropole.
 */
final readonly class RecordsResponse
{
    /**
     * @param list<ClosureRecord> $results
     */
    public function __construct(
        public array $results,
    ) {
    }
}

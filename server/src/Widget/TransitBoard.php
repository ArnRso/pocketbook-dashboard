<?php

declare(strict_types=1);

namespace App\Widget;

use App\Entity\TransitStop;
use App\Transit\Passage;

/**
 * Prochains passages à un arrêt, dans sa ligne et sa direction.
 */
final readonly class TransitBoard
{
    /**
     * @param list<Passage> $passages
     */
    public function __construct(
        public TransitStop $stop,
        public array $passages,
    ) {
    }
}

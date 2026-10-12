<?php

declare(strict_types=1);

namespace App\Widget;

final readonly class TransitView
{
    /**
     * @param list<TransitBoard> $boards
     */
    public function __construct(
        public array $boards,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Transit;

/**
 * Option de la liste « Ligne et direction », au format de TomSelect.
 */
final readonly class RouteChoice
{
    public function __construct(
        public string $value,
        public string $text,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Screen;

/**
 * Type de tuile proposé dans l'éditeur.
 */
final readonly class TileTypeChoice
{
    public function __construct(
        public string $widget,
        public string $label,
    ) {
    }
}

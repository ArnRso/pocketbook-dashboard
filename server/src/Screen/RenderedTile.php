<?php

declare(strict_types=1);

namespace App\Screen;

use App\Entity\Tile\Tile;

/**
 * Tuile affichée à l'écran, avec le HTML de son widget.
 */
final readonly class RenderedTile
{
    public function __construct(
        public Tile $tile,
        public string $html,
    ) {
    }
}

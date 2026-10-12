<?php

declare(strict_types=1);

namespace App\Screen;

/**
 * Ligne de la mise en page automatique : une tuile, ou deux en demi-largeur.
 */
final readonly class LayoutRow
{
    /**
     * @param non-empty-list<RenderedTile> $tiles
     * @param positive-int                 $weight part de la hauteur de l'écran
     */
    public function __construct(
        public array $tiles,
        public int $weight,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Screen;

/**
 * Ligne de la mise en page automatique : une tuile, ou deux côte à côte.
 */
final readonly class LayoutRow
{
    /**
     * @param non-empty-list<RenderedTile> $tiles
     * @param float                        $share part de la hauteur de l'écran (les lignes totalisent 1 au plus)
     */
    public function __construct(
        public array $tiles,
        public float $share,
    ) {
    }
}

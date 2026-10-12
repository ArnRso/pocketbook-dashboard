<?php

declare(strict_types=1);

namespace App\Screen;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Position et taille d'une tuile envoyées par l'éditeur de grille.
 */
final readonly class TileLayout
{
    public function __construct(
        #[Assert\Range(min: 1, max: Grid::COLUMNS)]
        public int $column,
        #[Assert\Range(min: 1, max: Grid::ROWS)]
        public int $row,
        #[Assert\Range(min: 1, max: Grid::COLUMNS)]
        public int $width,
        #[Assert\Range(min: 1, max: Grid::ROWS)]
        public int $height,
    ) {
    }
}

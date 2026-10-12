<?php

declare(strict_types=1);

namespace App\Entity\Tile;

/**
 * Largeur imposée à une tuile en mise en page automatique.
 */
enum TileWidth: string
{
    case Full = 'full';
    case Half = 'half';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Pleine largeur',
            self::Half => 'Demi-largeur',
        };
    }
}

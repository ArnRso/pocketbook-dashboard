<?php

declare(strict_types=1);

namespace App\Entity\Tile;

/**
 * Hauteur imposée à une tuile en mise en page automatique, en huitièmes de l'écran.
 */
enum TileSize: int
{
    case Small = 1;
    case Medium = 2;
    case Large = 3;

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Petite',
            self::Medium => 'Moyenne',
            self::Large => 'Grande',
        };
    }

    /** Part de la hauteur de l'écran */
    public function fraction(): float
    {
        return $this->value / 8;
    }
}

<?php

declare(strict_types=1);

namespace App\Entity\Tile;

/**
 * Hauteur d'une tuile en mise en page automatique : les lignes affichées se partagent la hauteur de
 * l'écran en proportion de ce poids.
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

    /**
     * Hauteur correspondant à un nombre de lignes de la grille.
     */
    public static function fromRows(int $rows): self
    {
        return match (true) {
            $rows <= 2 => self::Small,
            3 === $rows => self::Medium,
            default => self::Large,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Widget;

use App\Entity\Tile\Tile;

/**
 * Une tuile confiée au mauvais widget : erreur de programmation, jamais affichée à l'utilisateur.
 */
final class UnsupportedTileException extends \LogicException
{
    public static function for(Tile $tile, string $expected): self
    {
        return new self(\sprintf('Tuile %s reçue, %s attendue.', $tile::class, $expected));
    }
}

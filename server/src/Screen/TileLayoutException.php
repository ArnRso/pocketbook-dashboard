<?php

declare(strict_types=1);

namespace App\Screen;

/**
 * Placement refusé : la tuile sort de la grille, en chevauche une autre, ou ne trouve pas de place libre.
 */
final class TileLayoutException extends \RuntimeException
{
}

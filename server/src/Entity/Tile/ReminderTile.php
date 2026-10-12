<?php

declare(strict_types=1);

namespace App\Entity\Tile;

use Doctrine\ORM\Mapping as ORM;

/**
 * Rappels en cours (rubrique Rappels de l'admin). La tuile disparaît de l'écran quand il n'y en a aucun.
 */
#[ORM\Entity]
class ReminderTile extends Tile
{
    public const WIDGET = 'reminders';

    public function widget(): string
    {
        return self::WIDGET;
    }

    public static function label(): string
    {
        return 'Rappels';
    }

    protected static function defaultWidth(): int
    {
        return 12;
    }

    protected static function defaultHeight(): int
    {
        return 2;
    }

    public static function maxHeight(): int
    {
        return 2;
    }
}

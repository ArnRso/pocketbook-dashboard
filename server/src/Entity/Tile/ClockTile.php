<?php

declare(strict_types=1);

namespace App\Entity\Tile;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ClockTile extends Tile
{
    public const WIDGET = 'clock';

    public function widget(): string
    {
        return self::WIDGET;
    }

    public static function label(): string
    {
        return 'Date et heure';
    }

    protected static function defaultWidth(): int
    {
        return 12;
    }

    protected static function defaultHeight(): int
    {
        return 2;
    }
}

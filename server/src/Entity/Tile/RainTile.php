<?php

declare(strict_types=1);

namespace App\Entity\Tile;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class RainTile extends WeatherTile
{
    public const WIDGET = 'rain';

    public function widget(): string
    {
        return self::WIDGET;
    }

    public static function label(): string
    {
        return 'Pluie';
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

<?php

declare(strict_types=1);

namespace App\Entity\Tile;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CurrentWeatherTile extends WeatherTile
{
    public const WIDGET = 'current_weather';

    public function widget(): string
    {
        return self::WIDGET;
    }

    public static function label(): string
    {
        return 'Météo actuelle';
    }

    protected static function defaultWidth(): int
    {
        return 12;
    }

    protected static function defaultHeight(): int
    {
        return 3;
    }

    public static function maxHeight(): int
    {
        return 3;
    }
}

<?php

declare(strict_types=1);

namespace App\Entity\Tile;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class HourlyForecastTile extends WeatherTile
{
    public const WIDGET = 'hourly_forecast';

    public function widget(): string
    {
        return self::WIDGET;
    }

    public static function label(): string
    {
        return 'Prévisions heure par heure';
    }

    protected static function defaultWidth(): int
    {
        return 12;
    }

    protected static function defaultHeight(): int
    {
        return 4;
    }
}

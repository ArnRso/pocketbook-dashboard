<?php

declare(strict_types=1);

namespace App\Widget;

use App\Entity\Device;
use App\Entity\Tile\Tile;
use App\Entity\Tile\WeatherTile;
use App\Weather\ForecastProvider;

/**
 * Base des widgets météo : ils ne diffèrent que par leur template.
 * Les prévisions sont toujours celles du moment présent, y compris pour un aperçu.
 */
abstract class AbstractWeatherWidget implements WidgetInterface
{
    public function __construct(
        private readonly ForecastProvider $forecasts,
    ) {
    }

    public function data(Tile $tile, \DateTimeImmutable $at, ?Device $device = null): WeatherView
    {
        if (!$tile instanceof WeatherTile) {
            throw UnsupportedTileException::for($tile, WeatherTile::class);
        }
        $place = $tile->getPlace() ?? throw new WidgetNotConfiguredException('Aucun lieu choisi pour cette tuile');

        return new WeatherView($place->getLabel(), $this->forecasts->forecast($place));
    }
}

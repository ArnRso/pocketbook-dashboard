<?php

declare(strict_types=1);

namespace App\Widget;

use App\Entity\Tile\HourlyForecastTile;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(HourlyForecastTile::WIDGET)]
final class HourlyForecastWidget extends AbstractWeatherWidget
{
    public function template(): string
    {
        return 'widget/hourly_forecast.html.twig';
    }
}

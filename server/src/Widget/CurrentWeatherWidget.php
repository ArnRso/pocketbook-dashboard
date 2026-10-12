<?php

declare(strict_types=1);

namespace App\Widget;

use App\Entity\Tile\CurrentWeatherTile;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(CurrentWeatherTile::WIDGET)]
final class CurrentWeatherWidget extends AbstractWeatherWidget
{
    public function template(): string
    {
        return 'widget/current_weather.html.twig';
    }
}

<?php

declare(strict_types=1);

namespace App\Widget;

use App\Entity\Tile\RainTile;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(RainTile::WIDGET)]
final class RainWidget extends AbstractWeatherWidget
{
    public function template(): string
    {
        return 'widget/rain.html.twig';
    }
}

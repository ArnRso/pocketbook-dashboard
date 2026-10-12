<?php

declare(strict_types=1);

namespace App\Widget;

use App\Entity\Tile\ClockTile;
use App\Entity\Tile\Tile;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(ClockTile::WIDGET)]
final class ClockWidget implements WidgetInterface
{
    public function template(): string
    {
        return 'widget/clock.html.twig';
    }

    public function data(Tile $tile, \DateTimeImmutable $at): ClockView
    {
        if (!$tile instanceof ClockTile) {
            throw UnsupportedTileException::for($tile, ClockTile::class);
        }

        return new ClockView($at);
    }
}

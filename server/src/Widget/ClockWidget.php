<?php

declare(strict_types=1);

namespace App\Widget;

use App\Entity\Device;
use App\Entity\Tile\ClockTile;
use App\Entity\Tile\Tile;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(ClockTile::WIDGET)]
final class ClockWidget implements WidgetInterface
{
    /** Au-delà, l'heure affichée devient la plage jusqu'au prochain rafraîchissement */
    private const PRECISE_REFRESH_SECONDS = 60;

    public function template(): string
    {
        return 'widget/clock.html.twig';
    }

    public function data(Tile $tile, \DateTimeImmutable $at, ?Device $device = null): ClockView
    {
        if (!$tile instanceof ClockTile) {
            throw UnsupportedTileException::for($tile, ClockTile::class);
        }

        $refresh = $device?->getRefreshSeconds() ?? 0;
        $until = $refresh > self::PRECISE_REFRESH_SECONDS ? $at->modify(\sprintf('+%d seconds', $refresh)) : null;

        return new ClockView($at, $until, $device?->getBatteryLevel(), $device?->isCharging() ?? false);
    }
}

<?php

declare(strict_types=1);

namespace App\Widget;

use App\Chaban\BridgeClosure;
use App\Chaban\ChabanClient;
use App\Entity\Device;
use App\Entity\Tile\ChabanTile;
use App\Entity\Tile\Tile;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;

#[AsTaggedItem(ChabanTile::WIDGET)]
final class ChabanWidget implements WidgetInterface
{
    public function __construct(
        private readonly ChabanClient $chaban,
        #[Target('cache.bridge')]
        private readonly CacheInterface $bridgeCache,
    ) {
    }

    public function template(): string
    {
        return 'widget/chaban.html.twig';
    }

    public function data(Tile $tile, \DateTimeImmutable $at, ?Device $device = null): ChabanView
    {
        if (!$tile instanceof ChabanTile) {
            throw UnsupportedTileException::for($tile, ChabanTile::class);
        }
        $days = $tile->getDays() ?? throw new WidgetNotConfiguredException('Nombre de jours non renseigné');

        // Une seule requête par jour et par heure pour toutes les tuiles, quel que soit leur nombre de jours
        $today = $at->setTime(0, 0);
        $key = CacheKey::of('chaban', (int) $today->format('Ymd'));
        $closures = $this->bridgeCache->get($key, fn (): array => $this->chaban->closures($today, $today->modify(\sprintf('+%d days', ChabanTile::MAX_DAYS + 1))));

        // En jours calendaires : aujourd'hui et les jours suivants, jusqu'à minuit
        $until = $today->modify(\sprintf('+%d days', $days));
        $upcoming = array_values(array_filter($closures, static fn (BridgeClosure $closure): bool => $closure->overlaps($at, $until)));
        $ongoing = array_find($upcoming, static fn (BridgeClosure $closure): bool => $closure->isOngoingAt($at));

        return new ChabanView($days, $upcoming, $ongoing);
    }
}

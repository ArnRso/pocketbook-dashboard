<?php

declare(strict_types=1);

namespace App\Widget;

use App\Entity\Device;
use App\Entity\Tile\CommuteTile;
use App\Entity\Tile\Tile;
use App\Traffic\Commute;
use App\Traffic\TravelTimeProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;

#[AsTaggedItem(CommuteTile::WIDGET)]
final class CommuteWidget implements WidgetInterface
{
    public function __construct(
        private readonly TravelTimeProviderInterface $travelTime,
        #[Target('cache.traffic')]
        private readonly CacheInterface $trafficCache,
    ) {
    }

    public function template(): string
    {
        return 'widget/commute.html.twig';
    }

    /**
     * Le temps de parcours reflète toujours le trafic actuel, y compris pour un aperçu.
     */
    public function data(Tile $tile, \DateTimeImmutable $at, ?Device $device = null): Commute
    {
        if (!$tile instanceof CommuteTile) {
            throw UnsupportedTileException::for($tile, CommuteTile::class);
        }
        $trip = $tile->getTrip() ?? throw new WidgetNotConfiguredException('Aucun trajet choisi pour cette tuile');

        $origin = $trip->getOrigin();
        $destination = $trip->getDestination();
        $from = $origin?->getCoordinates();
        $to = $destination?->getCoordinates();
        if (null === $origin || null === $destination || null === $from || null === $to) {
            throw new WidgetNotConfiguredException(\sprintf('Coordonnées manquantes pour le trajet %s', $trip));
        }

        $key = CacheKey::of('routes', $from->latitude, $from->longitude, $to->latitude, $to->longitude, (int) $trip->isAvoidTolls());
        $routes = $this->trafficCache->get($key, fn (): array => $this->travelTime->routes($from, $to, $trip->isAvoidTolls()));

        return new Commute($origin->getLabel(), $destination->getLabel(), $routes, $at);
    }
}

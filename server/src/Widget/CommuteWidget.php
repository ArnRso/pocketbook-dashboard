<?php

declare(strict_types=1);

namespace App\Widget;

use App\Repository\TripRepository;
use App\Traffic\Commute;
use App\Traffic\TravelTimeProviderInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;

#[AsTaggedItem('commute')]
final class CommuteWidget implements WidgetInterface
{
    public function __construct(
        private readonly TripRepository $trips,
        private readonly TravelTimeProviderInterface $travelTime,
        #[Target('cache.traffic')]
        private readonly CacheInterface $trafficCache,
        private readonly ClockInterface $clock,
    ) {
    }

    public function template(): string
    {
        return 'widget/commute.html.twig';
    }

    public function data(): CommuteView
    {
        $now = $this->clock->now();
        $trip = $this->trips->findActiveAt($now);
        if (null === $trip) {
            return new CommuteView(null);
        }

        $origin = $trip->getOrigin();
        $destination = $trip->getDestination();
        $from = $origin?->getCoordinates();
        $to = $destination?->getCoordinates();
        if (null === $origin || null === $destination || null === $from || null === $to) {
            throw new WidgetNotConfiguredException(\sprintf('Coordonnées manquantes pour le trajet %s', $trip));
        }

        $key = CacheKey::of('routes', $from->latitude, $from->longitude, $to->latitude, $to->longitude, (int) $trip->isAvoidTolls());
        $routes = $this->trafficCache->get($key, fn (): array => $this->travelTime->routes($from, $to, $trip->isAvoidTolls()));

        return new CommuteView(new Commute($origin->getLabel(), $destination->getLabel(), $routes, $now));
    }
}

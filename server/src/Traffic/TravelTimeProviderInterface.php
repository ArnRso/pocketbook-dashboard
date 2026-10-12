<?php

declare(strict_types=1);

namespace App\Traffic;

use App\Geo\Coordinates;

interface TravelTimeProviderInterface
{
    /**
     * @return non-empty-list<Route> itinéraires triés du plus rapide au plus lent
     *
     * @throws TrafficException
     */
    public function routes(Coordinates $from, Coordinates $to, bool $avoidTolls = false): array;
}

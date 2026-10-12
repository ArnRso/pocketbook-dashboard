<?php

declare(strict_types=1);

namespace App\Traffic\Waze;

final readonly class RouteResult
{
    /**
     * @param list<RouteSegment> $results
     */
    public function __construct(
        public array $results,
        public string $routeName = '',
    ) {
    }

    /**
     * Durée en secondes, avec ou sans le trafic temps réel.
     */
    public function seconds(bool $realTime = true): int
    {
        return array_sum(array_map(
            static fn (RouteSegment $segment) => $realTime ? $segment->crossTime : $segment->crossTimeWithoutRealTime,
            $this->results,
        ));
    }

    public function meters(): int
    {
        return array_sum(array_map(static fn (RouteSegment $segment) => $segment->length, $this->results));
    }
}

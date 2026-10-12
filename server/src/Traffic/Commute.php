<?php

declare(strict_types=1);

namespace App\Traffic;

final readonly class Commute
{
    /**
     * @param non-empty-list<Route> $routes itinéraires triés du plus rapide au plus lent
     */
    public function __construct(
        public string $origin,
        public string $destination,
        public array $routes,
        public \DateTimeImmutable $departure,
    ) {
    }

    public function best(): Route
    {
        return $this->routes[0];
    }

    public function arrival(): \DateTimeImmutable
    {
        return $this->departure->modify(\sprintf('+%d minutes', (int) round($this->best()->minutes)));
    }
}

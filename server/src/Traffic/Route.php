<?php

declare(strict_types=1);

namespace App\Traffic;

final readonly class Route
{
    public function __construct(
        public string $name,
        public float $minutes,
        public float $freeFlowMinutes,
        public float $kilometers,
    ) {
    }

    /**
     * Minutes perdues dans le trafic par rapport à un trajet sans circulation.
     */
    public function delay(): float
    {
        return max(0.0, $this->minutes - $this->freeFlowMinutes);
    }

    public function status(): TrafficStatus
    {
        return TrafficStatus::fromDelay($this->delay());
    }
}

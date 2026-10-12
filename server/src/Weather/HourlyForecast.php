<?php

declare(strict_types=1);

namespace App\Weather;

final readonly class HourlyForecast
{
    public function __construct(
        public \DateTimeImmutable $time,
        public float $temperature,
        public WeatherCondition $condition,
        public float $precipitation,
        public int $precipitationProbability,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Weather;

/**
 * Prévision pour une journée entière.
 */
final readonly class DailyForecast
{
    public function __construct(
        public \DateTimeImmutable $date,
        public WeatherCondition $condition,
        public float $temperatureMin,
        public float $temperatureMax,
        public float $precipitation,
        public int $precipitationProbability,
    ) {
    }
}

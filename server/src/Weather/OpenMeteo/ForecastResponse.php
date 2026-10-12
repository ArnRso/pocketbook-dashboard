<?php

declare(strict_types=1);

namespace App\Weather\OpenMeteo;

use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Réponse de l'endpoint /v1/forecast d'Open-Meteo.
 */
final readonly class ForecastResponse
{
    public function __construct(
        public CurrentConditions $current,
        public HourlySeries $hourly,
        #[SerializedName('minutely_15')]
        public QuarterHourlySeries $quarterHourly,
        public DailySeries $daily,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Weather\OpenMeteo;

use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class CurrentConditions
{
    public function __construct(
        #[SerializedName('temperature_2m')]
        public float $temperature,
        #[SerializedName('apparent_temperature')]
        public float $apparentTemperature,
        #[SerializedName('weather_code')]
        public int $weatherCode,
        #[SerializedName('wind_speed_10m')]
        public float $windSpeed,
        #[SerializedName('wind_gusts_10m')]
        public float $windGusts,
        #[SerializedName('relative_humidity_2m')]
        public int $humidity,
        #[SerializedName('is_day')]
        public int $isDay,
    ) {
    }
}

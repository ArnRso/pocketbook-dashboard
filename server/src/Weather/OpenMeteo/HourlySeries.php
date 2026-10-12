<?php

declare(strict_types=1);

namespace App\Weather\OpenMeteo;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

/**
 * Prévisions heure par heure, au format colonnes d'Open-Meteo (une liste par variable).
 */
final readonly class HourlySeries
{
    /**
     * @param list<\DateTimeImmutable> $time
     * @param list<float>              $temperature
     * @param list<float|null>         $precipitation
     * @param list<int|null>           $precipitationProbability
     * @param list<int>                $weatherCode
     * @param list<int>                $isDay
     */
    public function __construct(
        #[Context([DateTimeNormalizer::FORMAT_KEY => Series::DATETIME_FORMAT])]
        public array $time,
        #[SerializedName('temperature_2m')]
        public array $temperature,
        public array $precipitation,
        #[SerializedName('precipitation_probability')]
        public array $precipitationProbability,
        #[SerializedName('weather_code')]
        public array $weatherCode,
        #[SerializedName('is_day')]
        public array $isDay,
    ) {
    }
}

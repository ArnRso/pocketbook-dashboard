<?php

declare(strict_types=1);

namespace App\Weather\OpenMeteo;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

final readonly class DailySeries
{
    /**
     * @param list<\DateTimeImmutable> $time
     * @param list<float>              $temperatureMax
     * @param list<float>              $temperatureMin
     * @param list<float|null>         $precipitationSum
     * @param list<int|null>           $precipitationProbabilityMax
     * @param list<\DateTimeImmutable> $sunrise
     * @param list<\DateTimeImmutable> $sunset
     */
    public function __construct(
        #[Context([DateTimeNormalizer::FORMAT_KEY => Series::DATE_FORMAT])]
        public array $time,
        #[SerializedName('temperature_2m_max')]
        public array $temperatureMax,
        #[SerializedName('temperature_2m_min')]
        public array $temperatureMin,
        #[SerializedName('precipitation_sum')]
        public array $precipitationSum,
        #[SerializedName('precipitation_probability_max')]
        public array $precipitationProbabilityMax,
        #[Context([DateTimeNormalizer::FORMAT_KEY => Series::DATETIME_FORMAT])]
        public array $sunrise,
        #[Context([DateTimeNormalizer::FORMAT_KEY => Series::DATETIME_FORMAT])]
        public array $sunset,
    ) {
    }

    /**
     * Position du jour demandé dans les séries.
     */
    public function indexOf(\DateTimeImmutable $day): int
    {
        foreach ($this->time as $index => $date) {
            if ($date->format('Y-m-d') === $day->format('Y-m-d')) {
                return $index;
            }
        }

        throw new \UnexpectedValueException(\sprintf('Open-Meteo ne renvoie pas de prévision pour le %s.', $day->format('Y-m-d')));
    }
}

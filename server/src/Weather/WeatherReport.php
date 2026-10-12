<?php

declare(strict_types=1);

namespace App\Weather;

final readonly class WeatherReport
{
    /**
     * @param list<HourlyForecast>    $hours   prochaines heures, à partir de l'heure courante
     * @param list<PrecipitationStep> $nowcast pluie par quart d'heure sur les 2 prochaines heures
     */
    public function __construct(
        public float $temperature,
        public float $feelsLike,
        public WeatherCondition $condition,
        public float $windSpeed,
        public float $windGusts,
        public int $humidity,
        public float $temperatureMin,
        public float $temperatureMax,
        public float $precipitationToday,
        public int $precipitationProbability,
        public float $precipitationYesterday,
        public \DateTimeImmutable $sunrise,
        public \DateTimeImmutable $sunset,
        public DailyForecast $tomorrow,
        public array $hours,
        public array $nowcast,
    ) {
    }

    public function nowcastSummary(): string
    {
        if ([] === $this->nowcast) {
            return 'Prévision immédiate indisponible';
        }

        $rainingNow = $this->nowcast[0]->isRaining();
        foreach ($this->nowcast as $step) {
            if ($step->isRaining() !== $rainingNow) {
                $time = $step->time->format('H:i');

                return $rainingNow ? "Fin de la pluie vers {$time}" : "Pluie à partir de {$time}";
            }
        }

        return $rainingNow ? 'Pluie continue dans les 2 h' : 'Pas de pluie dans les 2 h';
    }
}

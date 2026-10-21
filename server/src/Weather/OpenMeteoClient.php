<?php

declare(strict_types=1);

namespace App\Weather;

use App\Geo\Coordinates;
use App\Weather\OpenMeteo\DailySeries;
use App\Weather\OpenMeteo\ForecastResponse;
use App\Weather\OpenMeteo\Series;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Prévisions Open-Meteo : gratuit, sans clé, et basé sur les modèles Météo-France en France.
 *
 * @see https://open-meteo.com/en/docs
 */
class OpenMeteoClient
{
    private const int HOURS = 12;

    public function __construct(
        #[Target('open_meteo.client')]
        private readonly HttpClientInterface $openMeteoClient,
        private readonly SerializerInterface $serializer,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws WeatherException
     */
    public function forecast(Coordinates $at): WeatherReport
    {
        $now = $this->clock->now();

        try {
            $json = $this->openMeteoClient->request('GET', '/v1/forecast', [
                'query' => [
                    'latitude' => $at->latitude,
                    'longitude' => $at->longitude,
                    'timezone' => $now->getTimezone()->getName(),
                    'past_days' => 1,
                    'forecast_days' => 2,
                    'current' => 'temperature_2m,apparent_temperature,weather_code,wind_speed_10m,wind_gusts_10m,relative_humidity_2m,is_day',
                    'hourly' => 'temperature_2m,precipitation,precipitation_probability,weather_code,is_day',
                    'minutely_15' => 'precipitation',
                    'forecast_minutely_15' => 8,
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,precipitation_probability_max,sunrise,sunset',
                ],
            ])->getContent();

            $forecast = $this->serializer->deserialize($json, ForecastResponse::class, 'json', [
                DateTimeNormalizer::TIMEZONE_KEY => $now->getTimezone(),
            ]);

            return $this->report($forecast, $now);
        } catch (HttpException $e) {
            throw new WeatherException('Open-Meteo est injoignable : '.$e->getMessage(), previous: $e);
        } catch (SerializerException|\UnexpectedValueException $e) {
            throw new WeatherException('Réponse inattendue d\'Open-Meteo : '.$e->getMessage(), previous: $e);
        }
    }

    private function report(ForecastResponse $forecast, \DateTimeImmutable $now): WeatherReport
    {
        $current = $forecast->current;
        $daily = $forecast->daily;
        $today = $daily->indexOf($now);
        $yesterday = $daily->indexOf($now->modify('-1 day'));

        return new WeatherReport(
            temperature: $current->temperature,
            feelsLike: $current->apparentTemperature,
            condition: WeatherCondition::fromWmoCode($current->weatherCode, 1 === $current->isDay),
            windSpeed: $current->windSpeed,
            windGusts: $current->windGusts,
            humidity: $current->humidity,
            temperatureMin: Series::at($daily->temperatureMin, $today),
            temperatureMax: Series::at($daily->temperatureMax, $today),
            precipitationToday: Series::at($daily->precipitationSum, $today) ?? 0.0,
            precipitationProbability: Series::at($daily->precipitationProbabilityMax, $today) ?? 0,
            precipitationYesterday: Series::at($daily->precipitationSum, $yesterday) ?? 0.0,
            sunrise: Series::at($daily->sunrise, $today),
            sunset: Series::at($daily->sunset, $today),
            tomorrow: self::day($daily, $daily->indexOf($now->modify('+1 day'))),
            hours: $this->hours($forecast, $now),
            nowcast: $this->nowcast($forecast),
        );
    }

    private static function day(DailySeries $daily, int $index): DailyForecast
    {
        return new DailyForecast(
            date: Series::at($daily->time, $index),
            condition: WeatherCondition::fromWmoCode(Series::at($daily->weatherCode, $index), true),
            temperatureMin: Series::at($daily->temperatureMin, $index),
            temperatureMax: Series::at($daily->temperatureMax, $index),
            precipitation: Series::at($daily->precipitationSum, $index) ?? 0.0,
            precipitationProbability: Series::at($daily->precipitationProbabilityMax, $index) ?? 0,
        );
    }

    /**
     * @return list<HourlyForecast>
     */
    private function hours(ForecastResponse $forecast, \DateTimeImmutable $now): array
    {
        $hourly = $forecast->hourly;
        $currentHour = new \DateTimeImmutable($now->format('Y-m-d H:00'), $now->getTimezone());
        $hours = [];

        foreach ($hourly->time as $index => $time) {
            if ($time < $currentHour) {
                continue;
            }

            $hours[] = new HourlyForecast(
                time: $time,
                temperature: Series::at($hourly->temperature, $index),
                condition: WeatherCondition::fromWmoCode(Series::at($hourly->weatherCode, $index), 1 === Series::at($hourly->isDay, $index)),
                precipitation: Series::at($hourly->precipitation, $index) ?? 0.0,
                precipitationProbability: Series::at($hourly->precipitationProbability, $index) ?? 0,
            );

            if (self::HOURS === \count($hours)) {
                break;
            }
        }

        return $hours;
    }

    /**
     * @return list<PrecipitationStep>
     */
    private function nowcast(ForecastResponse $forecast): array
    {
        $series = $forecast->quarterHourly;
        $steps = [];

        foreach ($series->time as $index => $time) {
            $steps[] = new PrecipitationStep($time, Series::at($series->precipitation, $index) ?? 0.0);
        }

        return $steps;
    }
}

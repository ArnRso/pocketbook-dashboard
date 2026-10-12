<?php

declare(strict_types=1);

namespace App\Tests\Weather;

use App\Geo\Coordinates;
use App\Weather\HourlyForecast;
use App\Weather\OpenMeteo\DailySeries;
use App\Weather\OpenMeteo\Series;
use App\Weather\OpenMeteoClient;
use App\Weather\PrecipitationStep;
use App\Weather\WeatherException;
use App\Weather\WeatherReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\SerializerInterface;

#[CoversClass(OpenMeteoClient::class)]
#[CoversClass(DailySeries::class)]
#[CoversClass(Series::class)]
final class OpenMeteoClientTest extends KernelTestCase
{
    public function testRequestAsksForEverythingTheDashboardNeeds(): void
    {
        $response = new MockResponse(self::fixture('open-meteo-forecast.json'));

        $this->client($response)->forecast(new Coordinates(45.7576, 4.832));

        self::assertSame('GET', $response->getRequestMethod());
        self::assertStringStartsWith('https://api.open-meteo.com/v1/forecast?', $response->getRequestUrl());
        self::assertSame([
            'latitude' => '45.7576',
            'longitude' => '4.832',
            'timezone' => 'Europe/Paris',
            'past_days' => '1',
            'forecast_days' => '2',
            'current' => 'temperature_2m,apparent_temperature,weather_code,wind_speed_10m,wind_gusts_10m,relative_humidity_2m,is_day',
            'hourly' => 'temperature_2m,precipitation,precipitation_probability,weather_code,is_day',
            'minutely_15' => 'precipitation',
            'forecast_minutely_15' => '8',
            'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum,precipitation_probability_max,sunrise,sunset',
        ], self::query($response));
    }

    public function testCurrentConditionsAndDailyValues(): void
    {
        $report = $this->forecast('open-meteo-forecast.json');

        self::assertSame(16.4, $report->temperature);
        self::assertSame(15.4, $report->feelsLike);
        self::assertSame('Ciel dégagé', $report->condition->label);
        self::assertSame('clear', $report->condition->icon);
        self::assertSame(4.0, $report->windSpeed);
        self::assertSame(10.4, $report->windGusts);
        self::assertSame(54, $report->humidity);
        self::assertSame(9.5, $report->temperatureMin);
        self::assertSame(19.3, $report->temperatureMax);
        self::assertSame('2026-10-12 07:53', $report->sunrise->format('Y-m-d H:i'));
        self::assertSame('2026-10-12 19:00', $report->sunset->format('Y-m-d H:i'));
    }

    public function testTwelveHoursStartingWithTheCurrentOne(): void
    {
        $report = $this->forecast('open-meteo-forecast.json');

        self::assertSame(
            ['13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00', '00:00'],
            array_map(static fn (HourlyForecast $hour): string => $hour->time->format('H:i'), $report->hours),
        );
        self::assertSame(
            [16.1, 17.5, 18.4, 19.1, 19.3, 19.0, 18.2, 17.1, 15.7, 14.3, 13.0, 12.2],
            array_map(static fn (HourlyForecast $hour): float => $hour->temperature, $report->hours),
        );
        self::assertSame(
            ['clear', 'clear', 'cloudy', 'clear', 'clear', 'clear', 'clear', 'clear-night', 'clear-night', 'clear-night', 'partly-night', 'cloudy'],
            array_map(static fn (HourlyForecast $hour): string => $hour->condition->icon, $report->hours),
        );
    }

    public function testCurrentHourIsIncludedOnTheHour(): void
    {
        $report = $this->forecast('open-meteo-forecast.json', '2026-10-12 13:00:00');

        self::assertSame('13:00', ($report->hours[0] ?? null)?->time->format('H:i'));
    }

    public function testPrecipitation(): void
    {
        $report = $this->forecast('open-meteo-forecast-rainy.json');

        self::assertSame(3.4, $report->precipitationToday);
        self::assertSame(80, $report->precipitationProbability);
        self::assertSame(1.2, $report->precipitationYesterday);

        $hour = $report->hours[0] ?? null;
        self::assertNotNull($hour);
        self::assertSame(0.7, $hour->precipitation);
        self::assertSame(65, $hour->precipitationProbability);

        self::assertSame(
            ['13:15', '13:30', '13:45', '14:00', '14:15', '14:30', '14:45', '15:00'],
            array_map(static fn (PrecipitationStep $step): string => $step->time->format('H:i'), $report->nowcast),
        );
        self::assertSame(
            [0.3, 0.2, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0],
            array_map(static fn (PrecipitationStep $step): float => $step->millimeters, $report->nowcast),
        );
    }

    public function testMissingPrecipitationFallsBackToZero(): void
    {
        $report = $this->forecast('open-meteo-forecast-without-precipitation.json');

        self::assertSame(0.0, $report->precipitationToday);
        self::assertSame(0, $report->precipitationProbability);
        self::assertSame(0.0, $report->precipitationYesterday);
        $hour = $report->hours[0] ?? null;
        self::assertNotNull($hour);
        self::assertSame(0.0, $hour->precipitation);
        self::assertSame(0, $hour->precipitationProbability);
        $step = $report->nowcast[0] ?? null;
        self::assertNotNull($step);
        self::assertSame(0.0, $step->millimeters);
    }

    public function testDatesUseTheClockTimezone(): void
    {
        $response = new MockResponse(self::fixture('open-meteo-forecast.json'));

        $report = $this->client($response, new MockClock('2026-10-12 12:20:00', 'Europe/London'))->forecast(new Coordinates(45.7576, 4.832));

        self::assertSame('Europe/London', self::query($response)['timezone'] ?? null);
        self::assertSame('Europe/London', $report->sunrise->getTimezone()->getName());
        self::assertSame('2026-10-12T12:00:00+01:00', ($report->hours[0] ?? null)?->time->format(\DATE_ATOM));
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadRaisesWeatherException(string $search, string $replace, string $expectedMessage): void
    {
        $json = str_replace($search, $replace, self::fixture('open-meteo-forecast.json'), $count);
        self::assertSame(1, $count, 'La fixture doit contenir le fragment à corrompre.');

        $this->expectException(WeatherException::class);
        $this->expectExceptionMessageMatches($expectedMessage);

        $this->client(new MockResponse($json))->forecast(new Coordinates(45.7576, 4.832));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'température manquante' => ['"temperature_2m":16.4,', '', '/^Réponse inattendue d\'Open-Meteo : .*temperature/'];
        yield 'mauvais type' => ['"weather_code":0,', '"weather_code":"soleil",', '/^Réponse inattendue d\'Open-Meteo : .*weatherCode/'];
        yield 'date illisible' => ['"time":["2026-10-11T00:00"', '"time":["demain"', '/^Réponse inattendue d\'Open-Meteo : .*demain/'];
        yield 'série incomplète' => ['"temperature_2m":[', '"temperature_2m":[12.5],"ignored":[', '/^Réponse inattendue d\'Open-Meteo : Série Open-Meteo incomplète : aucune valeur en position 37\.$/'];
        yield 'jour absent' => ['"time":["2026-10-11","2026-10-12","2026-10-13"]', '"time":["2026-10-01","2026-10-02","2026-10-03"]', '/^Réponse inattendue d\'Open-Meteo : Open-Meteo ne renvoie pas de prévision pour le 2026-10-12\.$/'];
        yield 'pas du JSON' => ['{"current"', '<html>{"current"', '/^Réponse inattendue d\'Open-Meteo : /'];
    }

    public function testHttpErrorRaisesWeatherException(): void
    {
        $client = $this->client(new MockResponse('', ['http_code' => 503]));

        $this->expectException(WeatherException::class);
        $this->expectExceptionMessageMatches('/^Open-Meteo est injoignable : HTTP 503 returned for "https:\/\/api\.open-meteo\.com\/v1\/forecast\?.+"\.$/');

        $client->forecast(new Coordinates(45.7576, 4.832));
    }

    private function forecast(string $fixture, string $now = '2026-10-12 13:20:00'): WeatherReport
    {
        return $this->client(new MockResponse(self::fixture($fixture)), new MockClock($now, 'Europe/Paris'))->forecast(new Coordinates(45.7576, 4.832));
    }

    private function client(MockResponse $response, ?MockClock $clock = null): OpenMeteoClient
    {
        return new OpenMeteoClient(
            new MockHttpClient($response, 'https://api.open-meteo.com'),
            self::getContainer()->get(SerializerInterface::class),
            $clock ?? new MockClock('2026-10-12 13:20:00', 'Europe/Paris'),
        );
    }

    private static function fixture(string $name): string
    {
        $json = file_get_contents(__DIR__.'/../fixtures/'.$name);
        self::assertIsString($json);

        return $json;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function query(MockResponse $response): array
    {
        parse_str((string) parse_url($response->getRequestUrl(), \PHP_URL_QUERY), $query);

        return $query;
    }
}

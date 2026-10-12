<?php

declare(strict_types=1);

namespace App\Tests\Weather;

use App\Weather\PrecipitationStep;
use App\Weather\WeatherCondition;
use App\Weather\WeatherReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(WeatherReport::class)]
#[CoversClass(WeatherCondition::class)]
#[CoversClass(PrecipitationStep::class)]
final class WeatherReportTest extends TestCase
{
    /**
     * @param list<float> $millimeters pluie par quart d'heure à partir de 13:00
     */
    #[DataProvider('nowcasts')]
    public function testNowcastSummary(array $millimeters, string $expected): void
    {
        self::assertSame($expected, self::report($millimeters)->nowcastSummary());
    }

    public static function nowcasts(): iterable
    {
        yield 'sec' => [[0, 0, 0, 0, 0, 0, 0, 0], 'Pas de pluie dans les 2 h'];
        yield 'pluie qui arrive' => [[0, 0, 0.2, 0.5, 0, 0, 0, 0], 'Pluie à partir de 13:30'];
        yield 'pluie qui s\'arrête' => [[0.4, 0.3, 0.1, 0, 0, 0, 0, 0], 'Fin de la pluie vers 13:45'];
        yield 'pluie continue' => [[0.4, 0.3, 0.1, 0.2, 0.5, 1, 1, 1], 'Pluie continue dans les 2 h'];
        yield 'averse immédiate' => [[0.4, 0, 0, 0, 0, 0, 0, 0], 'Fin de la pluie vers 13:15'];
        yield 'un seul pas sec' => [[0], 'Pas de pluie dans les 2 h'];
        yield 'sans données' => [[], 'Prévision immédiate indisponible'];
    }

    public function testNightVariantOnlyAppliesToClearSkies(): void
    {
        self::assertSame('clear-night', WeatherCondition::fromWmoCode(0, false)->icon);
        self::assertSame('partly-night', WeatherCondition::fromWmoCode(2, false)->icon);
        self::assertSame('rain', WeatherCondition::fromWmoCode(63, false)->icon);
        self::assertSame('clear', WeatherCondition::fromWmoCode(0, true)->icon);
        self::assertSame('partly', WeatherCondition::fromWmoCode(2, true)->icon);
        self::assertSame('Inconnu', WeatherCondition::fromWmoCode(1234, true)->label);
        self::assertSame('cloudy', WeatherCondition::fromWmoCode(1234, true)->icon);
    }

    /**
     * @param list<float> $millimeters
     */
    private static function report(array $millimeters): WeatherReport
    {
        $start = new \DateTimeImmutable('2026-10-12 13:00');

        return new WeatherReport(
            temperature: 16,
            feelsLike: 15,
            condition: WeatherCondition::fromWmoCode(0, true),
            windSpeed: 4,
            windGusts: 10,
            humidity: 54,
            temperatureMin: 10,
            temperatureMax: 19,
            precipitationToday: 0,
            precipitationProbability: 0,
            precipitationYesterday: 0,
            sunrise: $start->setTime(7, 53),
            sunset: $start->setTime(19, 0),
            hours: [],
            nowcast: array_map(
                static fn (int $i, float $mm) => new PrecipitationStep($start->modify(\sprintf('+%d minutes', 15 * $i)), $mm),
                array_keys($millimeters),
                $millimeters,
            ),
        );
    }
}

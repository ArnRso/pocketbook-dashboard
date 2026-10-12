<?php

declare(strict_types=1);

namespace App\Tests\Widget;

use App\Entity\Device;
use App\Entity\Place;
use App\Entity\Tile\ClockTile;
use App\Entity\Tile\CommuteTile;
use App\Entity\Tile\CurrentWeatherTile;
use App\Entity\Tile\HourlyForecastTile;
use App\Entity\Tile\RainTile;
use App\Entity\Tile\WeatherTile;
use App\Entity\Trip;
use App\Geo\Coordinates;
use App\Traffic\Route;
use App\Traffic\TravelTimeProviderInterface;
use App\Weather\ForecastProvider;
use App\Weather\OpenMeteoClient;
use App\Widget\AbstractWeatherWidget;
use App\Widget\CacheKey;
use App\Widget\ClockView;
use App\Widget\ClockWidget;
use App\Widget\CommuteWidget;
use App\Widget\CurrentWeatherWidget;
use App\Widget\HourlyForecastWidget;
use App\Widget\RainWidget;
use App\Widget\UnsupportedTileException;
use App\Widget\WidgetNotConfiguredException;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\SerializerInterface;

#[CoversClass(ClockWidget::class)]
#[CoversClass(ClockView::class)]
#[CoversClass(AbstractWeatherWidget::class)]
#[CoversClass(CurrentWeatherWidget::class)]
#[CoversClass(RainWidget::class)]
#[CoversClass(HourlyForecastWidget::class)]
#[CoversClass(CommuteWidget::class)]
#[CoversClass(ForecastProvider::class)]
#[CoversClass(CacheKey::class)]
#[CoversClass(UnsupportedTileException::class)]
final class WidgetsTest extends KernelTestCase
{
    private const AT = '2026-10-12 08:00';

    public function testClockShowsTheDisplayedMoment(): void
    {
        $view = (new ClockWidget())->data(new ClockTile(), new \DateTimeImmutable(self::AT));

        self::assertSame(self::AT, $view->moment->format('Y-m-d H:i'));
        self::assertNull($view->until);
        self::assertNull($view->battery);
        self::assertFalse($view->charging);
        self::assertSame('widget/clock.html.twig', (new ClockWidget())->template());
    }

    public function testClockShowsHowLongTheScreenIsValidAndTheBattery(): void
    {
        $device = (new Device())->setRefreshSeconds(600)->reportBattery(87, true);

        $view = (new ClockWidget())->data(new ClockTile(), new \DateTimeImmutable(self::AT), $device);

        self::assertSame('2026-10-12 08:10', $view->until?->format('Y-m-d H:i'));
        self::assertSame([87, true], [$view->battery, $view->charging]);
    }

    public function testClockShowsOnlyTheTimeWhenTheScreenIsRefreshedEveryMinute(): void
    {
        $view = (new ClockWidget())->data(new ClockTile(), new \DateTimeImmutable(self::AT), (new Device())->setRefreshSeconds(60));

        self::assertNull($view->until);
        self::assertNull($view->battery);
    }

    public function testWeatherTilesOfTheSamePlaceShareOneForecast(): void
    {
        $http = $this->openMeteo();
        $forecasts = $this->forecasts($http);
        $home = self::home();

        $current = (new CurrentWeatherWidget($forecasts))->data((new CurrentWeatherTile())->setPlace($home), new \DateTimeImmutable(self::AT));
        $rain = (new RainWidget($forecasts))->data((new RainTile())->setPlace($home), new \DateTimeImmutable(self::AT));
        (new HourlyForecastWidget($forecasts))->data((new HourlyForecastTile())->setPlace($home), new \DateTimeImmutable(self::AT));

        self::assertSame(1, $http->getRequestsCount());
        self::assertSame('Maison', $current->place);
        self::assertSame(16.4, $current->report->temperature);
        self::assertEquals($current->report, $rain->report);
    }

    public function testEachWeatherWidgetHasItsTemplate(): void
    {
        $forecasts = $this->forecasts($this->openMeteo());

        self::assertSame('widget/current_weather.html.twig', (new CurrentWeatherWidget($forecasts))->template());
        self::assertSame('widget/rain.html.twig', (new RainWidget($forecasts))->template());
        self::assertSame('widget/hourly_forecast.html.twig', (new HourlyForecastWidget($forecasts))->template());
    }

    public function testWeatherTileWithoutPlaceIsReported(): void
    {
        $widget = new RainWidget($this->forecasts($this->openMeteo()));

        $this->expectException(WidgetNotConfiguredException::class);
        $this->expectExceptionMessageIs('Aucun lieu choisi pour cette tuile');

        $widget->data(new RainTile(), new \DateTimeImmutable(self::AT));
    }

    public function testPlaceWithoutCoordinatesIsReported(): void
    {
        $widget = new RainWidget($this->forecasts($this->openMeteo()));

        $this->expectException(WidgetNotConfiguredException::class);
        $this->expectExceptionMessageIs('Coordonnées manquantes pour le lieu Chalet');

        $widget->data((new RainTile())->setPlace((new Place())->setLabel('Chalet')), new \DateTimeImmutable(self::AT));
    }

    public function testRoutesAreFetchedOncePerCacheLifetimeAndTrip(): void
    {
        $travelTime = $this->createMock(TravelTimeProviderInterface::class);
        $travelTime->expects(self::exactly(2))
            ->method('routes')
            ->with(new Coordinates(45.7576, 4.832), new Coordinates(45.7719, 4.8902), self::isBool())
            ->willReturn([new Route('A7', 19, 18, 9.6)]);
        $widget = new CommuteWidget($travelTime, new ArrayAdapter());
        $trip = (new Trip())->setOrigin(self::home())->setDestination(self::work());
        $tile = (new CommuteTile())->setTrip($trip);

        $commute = $widget->data($tile, new \DateTimeImmutable(self::AT));
        $widget->data($tile, new \DateTimeImmutable(self::AT));
        // Changer l'option péages change d'itinéraire : nouvel appel
        $trip->setAvoidTolls(true);
        $widget->data($tile, new \DateTimeImmutable(self::AT));

        self::assertSame('Maison', $commute->origin);
        self::assertSame('Travail', $commute->destination);
        self::assertSame('08:19', $commute->arrival()->format('H:i'));
        self::assertSame('widget/commute.html.twig', $widget->template());
    }

    public function testCommuteTileWithoutTripIsReported(): void
    {
        $widget = new CommuteWidget(self::createStub(TravelTimeProviderInterface::class), new ArrayAdapter());

        $this->expectException(WidgetNotConfiguredException::class);
        $this->expectExceptionMessageIs('Aucun trajet choisi pour cette tuile');

        $widget->data(new CommuteTile(), new \DateTimeImmutable(self::AT));
    }

    public function testTripWithoutCoordinatesIsReported(): void
    {
        $widget = new CommuteWidget(self::createStub(TravelTimeProviderInterface::class), new ArrayAdapter());
        $trip = (new Trip())->setOrigin(self::home())->setDestination((new Place())->setLabel('Travail'));

        $this->expectException(WidgetNotConfiguredException::class);
        $this->expectExceptionMessageIs('Coordonnées manquantes pour le trajet Maison → Travail');

        $widget->data((new CommuteTile())->setTrip($trip), new \DateTimeImmutable(self::AT));
    }

    public function testClockRejectsOtherTiles(): void
    {
        $this->expectException(UnsupportedTileException::class);
        $this->expectExceptionMessageIs(\sprintf('Tuile %s reçue, %s attendue.', RainTile::class, ClockTile::class));

        (new ClockWidget())->data(new RainTile(), new \DateTimeImmutable(self::AT));
    }

    public function testWeatherWidgetRejectsOtherTiles(): void
    {
        $this->expectException(UnsupportedTileException::class);
        $this->expectExceptionMessageIs(\sprintf('Tuile %s reçue, %s attendue.', ClockTile::class, WeatherTile::class));

        (new RainWidget($this->forecasts($this->openMeteo())))->data(new ClockTile(), new \DateTimeImmutable(self::AT));
    }

    public function testCommuteWidgetRejectsOtherTiles(): void
    {
        $this->expectException(UnsupportedTileException::class);
        $this->expectExceptionMessageIs(\sprintf('Tuile %s reçue, %s attendue.', ClockTile::class, CommuteTile::class));

        (new CommuteWidget(self::createStub(TravelTimeProviderInterface::class), new ArrayAdapter()))->data(new ClockTile(), new \DateTimeImmutable(self::AT));
    }

    private function openMeteo(): MockHttpClient
    {
        $json = file_get_contents(__DIR__.'/../fixtures/open-meteo-forecast.json');
        self::assertIsString($json);

        return new MockHttpClient([new MockResponse($json)], 'https://api.open-meteo.com');
    }

    private function forecasts(MockHttpClient $http): ForecastProvider
    {
        $clock = new MockClock('2026-10-12 13:20:00', 'Europe/Paris');

        return new ForecastProvider(new OpenMeteoClient($http, self::getContainer()->get(SerializerInterface::class), $clock), new ArrayAdapter());
    }

    private static function home(): Place
    {
        return (new Place())->setLabel('Maison')->setLatitude(45.7576)->setLongitude(4.832);
    }

    private static function work(): Place
    {
        return (new Place())->setLabel('Travail')->setLatitude(45.7719)->setLongitude(4.8902);
    }
}

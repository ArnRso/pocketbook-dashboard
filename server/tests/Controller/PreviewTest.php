<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\DashboardController;
use App\Entity\Dashboard;
use App\Entity\Reminder;
use App\Entity\ReminderWindow;
use App\Entity\Tile\ClockTile;
use App\Entity\Tile\ReminderTile;
use App\Entity\Tile\WeatherTile;
use App\Entity\Weekday;
use App\Repository\DashboardRepository;
use App\Screen\PreviewMoment;
use App\Screen\PreviewQuery;
use App\Screen\RenderedTile;
use App\Screen\ScreenRenderer;
use App\Weather\ForecastProvider;
use App\Widget\AbstractWeatherWidget;
use App\Widget\CacheKey;
use App\Widget\ClockWidget;
use App\Widget\CommuteWidget;
use App\Widget\ReminderWidget;
use App\Widget\WidgetRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(DashboardController::class)]
#[CoversClass(ScreenRenderer::class)]
#[CoversClass(RenderedTile::class)]
#[CoversClass(ReminderWidget::class)]
#[CoversClass(PreviewQuery::class)]
#[CoversClass(PreviewMoment::class)]
#[CoversClass(DashboardRepository::class)]
#[CoversClass(WidgetRenderer::class)]
#[CoversClass(ClockWidget::class)]
#[CoversClass(AbstractWeatherWidget::class)]
#[CoversClass(CommuteWidget::class)]
#[CoversClass(ForecastProvider::class)]
#[CoversClass(CacheKey::class)]
final class PreviewTest extends FunctionalTestCase
{
    public function testPreviewShowsEveryVisibleTile(): void
    {
        $this->createDashboard();
        $this->logIn();

        $this->client->request('GET', '/preview');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(5, '.tile');
        self::assertSelectorTextContains('.tile-clock .clock-date', 'Lundi 12 octobre');
        // Sans liseuse qui affiche ce dashboard : l'heure seule, sans batterie
        self::assertSelectorTextSame('.tile-clock .clock-time', '13:20');
        self::assertSelectorNotExists('.clock-battery');
        self::assertSelectorTextContains('.tile-current_weather .current-temp', '16°');
        self::assertSelectorTextContains('.tile-current_weather .current-condition', 'Ciel dégagé');
        self::assertSelectorTextSame('.tile-current_weather .current-tomorrow', 'Demain Couvert 11° / 19° 0,0 mm · 3 %');
        self::assertSelectorTextContains('.tile-rain .tile-title', 'Pluie · Maison');
        self::assertSelectorTextContains('.tile-rain .rain-summary', 'Pas de pluie dans les 2 h');
        self::assertSelectorCount(12, '.tile-hourly_forecast .hour');
        self::assertSelectorTextContains('.tile-commute .tile-title', 'Maison → Travail');
        self::assertSelectorTextContains('.tile-commute .commute-duration', '19');
        self::assertSelectorTextContains('.tile-commute .status', 'Trafic fluide');
        self::assertSelectorTextContains('.tile-commute', 'Arrivée vers 13:39');
        self::assertSelectorTextContains('.tile-commute', '9,6 km par Boulevard Périphérique Nord');
        self::assertSelectorNotExists('.unavailable');
    }

    public function testPreviewShowsTheBatteryAndRefreshOfTheDeviceShowingTheDashboard(): void
    {
        [$device] = $this->createDevice(dashboard: $this->createDashboard());
        $device->reportBattery(42, false);
        $this->entityManager()->flush();
        $this->logIn();

        $this->client->request('GET', '/preview');

        // La liseuse se rafraîchit toutes les 10 minutes : l'écran vaut jusqu'à 13:30
        self::assertSelectorTextSame('.tile-clock .clock-time', '13:20 – 13:30');
        self::assertSelectorTextSame('.tile-clock .clock-battery', '42 %');
        self::assertSelectorNotExists('.clock-battery path');

        $device->reportBattery(43, true);
        $this->entityManager()->flush();
        $this->client->request('GET', '/preview');

        self::assertSelectorExists('.clock-battery path');
    }

    public function testDisabledDeviceIsIgnoredByThePreview(): void
    {
        [$device] = $this->createDevice(enabled: false, dashboard: $this->createDashboard());
        $device->reportBattery(42, false);
        $this->entityManager()->flush();
        $this->logIn();

        $this->client->request('GET', '/preview');

        self::assertSelectorTextSame('.tile-clock .clock-time', '13:20');
        self::assertSelectorNotExists('.clock-battery');
    }

    public function testTilesArePlacedOnTheGrid(): void
    {
        $this->createDashboard();
        $this->logIn();

        $crawler = $this->client->request('GET', '/preview');

        self::assertSame('grid-column: 1 / span 12; grid-row: 8 / span 4', $crawler->filter('.tile-hourly_forecast')->attr('style'));
    }

    public function testWithoutDashboard(): void
    {
        $this->logIn();

        $this->client->request('GET', '/preview');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.empty', 'Aucun dashboard n\'est assigné à cette liseuse.');
    }

    public function testDashboardCanBeChosen(): void
    {
        $this->createDashboard();
        $other = new Dashboard()->setName('Cuisine')->addTile(new ClockTile()->place(1, 1, 6, 2));
        $this->entityManager()->persist($other);
        $this->entityManager()->flush();
        $this->logIn();

        $this->client->request('GET', '/preview');
        self::assertSelectorCount(1, '.tile', 'Sans choix, le premier dashboard par ordre alphabétique est affiché.');

        $this->client->request('GET', \sprintf('/preview?dashboard=%d', (int) $other->getId()));
        self::assertSelectorCount(1, '.tile');
        self::assertSelectorExists('.tile-clock');
    }

    public function testTileOutsideItsScheduleIsNotShown(): void
    {
        $this->createDashboard();
        $this->logIn();
        self::mockTime('2026-10-12 21:00:00');

        $this->client->request('GET', '/preview');

        self::assertSelectorCount(4, '.tile');
        self::assertSelectorNotExists('.tile-commute');
    }

    public function testWazeFailureOnlyAffectsTheCommuteTile(): void
    {
        $this->createDashboard();
        $this->logIn();
        $this->routingResponse = new MockResponse('', ['http_code' => 403]);

        $this->client->request('GET', '/preview');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.tile-commute .unavailable .tile-title', 'Trajet · Maison → Travail');
        self::assertSelectorTextContains('.tile-commute .unavailable', 'Waze a refusé la requête (HTTP 403).');
        self::assertSelectorTextContains('.tile-current_weather .current-condition', 'Ciel dégagé');
    }

    public function testOpenMeteoFailureOnlyAffectsTheWeatherTiles(): void
    {
        $this->createDashboard();
        $this->logIn();
        $this->weatherResponse = new MockResponse('', ['http_code' => 500]);

        $this->client->request('GET', '/preview');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, '.unavailable');
        self::assertSelectorTextContains('.tile-current_weather .unavailable', 'Open-Meteo est injoignable');
        self::assertSelectorTextContains('.tile-commute .tile-title', 'Maison → Travail');
    }

    public function testPlaceWithoutCoordinatesIsReported(): void
    {
        $dashboard = $this->createDashboard();
        foreach ($dashboard->getTiles() as $tile) {
            if ($tile instanceof WeatherTile) {
                $tile->getPlace()?->setLatitude(null);
            }
        }
        $this->entityManager()->flush();
        $this->logIn();

        $this->client->request('GET', '/preview');

        self::assertSelectorTextContains('.tile-rain .unavailable', 'Coordonnées manquantes pour le lieu Maison');
    }

    public function testTripWithoutCoordinatesIsReported(): void
    {
        $this->createDashboard(withCoordinates: false);
        $this->logIn();

        $this->client->request('GET', '/preview');

        self::assertSelectorTextContains('.tile-commute .unavailable', 'Coordonnées manquantes pour le trajet Maison → Travail');
    }

    public function testPreviewOnAChosenDayAndTimeShowsTheTilesOfThatMoment(): void
    {
        $this->createDashboard();
        $this->logIn();

        $this->client->request('GET', '/preview?day=2&time=21:30');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.clock-date', 'Mardi 13 octobre');
        self::assertSelectorTextSame('.clock-time', '21:30');
        self::assertSelectorNotExists('.tile-commute');
        // La météo reste celle du moment présent
        self::assertSelectorTextContains('.tile-current_weather .current-temp', '16°');

        $this->client->request('GET', '/preview?day=2&time=07:00');

        self::assertSelectorTextContains('.tile-commute .tile-title', 'Maison → Travail');
        self::assertSelectorTextContains('.tile-commute', 'Arrivée vers 07:19');
    }

    public function testDayOrTimeAloneKeepsTheOtherOneCurrent(): void
    {
        $this->createDashboard();
        $this->logIn();

        $this->client->request('GET', '/preview?time=07:00');
        self::assertSelectorTextContains('.clock-date', 'Lundi 12 octobre');
        self::assertSelectorTextSame('.clock-time', '07:00');

        $this->client->request('GET', '/preview?day=7');
        self::assertSelectorTextContains('.clock-date', 'Dimanche 18 octobre');
        self::assertSelectorTextSame('.clock-time', '13:20');
    }

    public function testRemindersTileOnlyAppearsDuringAReminder(): void
    {
        $reminder = new Reminder()->setText('Sortir la poubelle verte')->addWindow(new ReminderWindow()
            ->setStartDay(Weekday::Sunday)
            ->setStartTime(new \DateTimeImmutable('16:00'))
            ->setEndDay(Weekday::Monday)
            ->setEndTime(new \DateTimeImmutable('09:00')));
        $dashboard = new Dashboard()
            ->setName('Entrée')
            ->addTile(new ClockTile()->place(1, 1, 12, 2))
            ->addTile(new ReminderTile()->place(1, 3, 12, 2));
        $this->entityManager()->persist($reminder);
        $this->entityManager()->persist($dashboard);
        $this->entityManager()->flush();
        $this->logIn();

        $this->client->request('GET', \sprintf('/preview?dashboard=%d&day=7&time=17:00', (int) $dashboard->getId()));

        self::assertSelectorCount(2, '.tile');
        self::assertSelectorTextSame('.tile-reminders .tile-title', 'Rappel');
        self::assertSelectorTextSame('.tile-reminders .reminder-text', 'Sortir la poubelle verte');
        self::assertSelectorTextSame('.tile-reminders .reminder-until', 'jusqu\'à lundi 09:00');

        $this->client->request('GET', \sprintf('/preview?dashboard=%d&day=1&time=10:00', (int) $dashboard->getId()));

        self::assertSelectorCount(1, '.tile');
        self::assertSelectorNotExists('.tile-reminders');
    }

    #[DataProvider('invalidQueries')]
    public function testInvalidQueryIsRejected(string $query): void
    {
        $this->logIn();

        $this->client->request('GET', '/preview?'.$query);

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'jour inexistant' => ['day=8'];
        yield 'jour zéro' => ['day=0'];
        yield 'heure hors quart d\'heure' => ['time=08:10'];
        yield 'heure invalide' => ['time=25:00'];
        yield 'dashboard invalide' => ['dashboard=0'];
    }

    public function testPreviewImage(): void
    {
        $this->createDashboard();
        $this->logIn();

        $this->client->request('GET', '/preview.png?day=2&time=07:00');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
    }
}

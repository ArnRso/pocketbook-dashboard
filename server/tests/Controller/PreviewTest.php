<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\DashboardController;
use App\Entity\Place;
use App\Repository\PlaceRepository;
use App\Repository\TripRepository;
use App\Screen\PreviewMoment;
use App\Screen\PreviewQuery;
use App\Screen\ScreenRenderer;
use App\Widget\CacheKey;
use App\Widget\CommuteView;
use App\Widget\CommuteWidget;
use App\Widget\WeatherView;
use App\Widget\WeatherWidget;
use App\Widget\WidgetRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(DashboardController::class)]
#[CoversClass(ScreenRenderer::class)]
#[CoversClass(PreviewQuery::class)]
#[CoversClass(PreviewMoment::class)]
#[CoversClass(WidgetRenderer::class)]
#[CoversClass(WeatherWidget::class)]
#[CoversClass(WeatherView::class)]
#[CoversClass(CommuteWidget::class)]
#[CoversClass(CommuteView::class)]
#[CoversClass(CacheKey::class)]
#[CoversClass(PlaceRepository::class)]
#[CoversClass(TripRepository::class)]
final class PreviewTest extends FunctionalTestCase
{
    public function testPreviewShowsWeatherAndCommute(): void
    {
        $this->createCommute();
        $this->logIn();

        $this->client->request('GET', '/preview');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.date', 'Lundi 12 octobre');
        self::assertSelectorTextContains('.meta', 'Mis à jour à 13:20');
        self::assertSelectorTextContains('.widget-weather .temp', '16°');
        self::assertSelectorTextContains('.widget-weather .condition', 'Ciel dégagé');
        self::assertSelectorTextContains('.widget-weather h2', 'Pluie · Maison');
        self::assertSelectorTextContains('.widget-weather .summary', 'Pas de pluie dans les 2 h');
        self::assertSelectorCount(12, '.widget-weather .hours > div');
        self::assertSelectorTextContains('.widget-commute h2', 'Maison → Travail');
        self::assertSelectorTextContains('.widget-commute .duration', '19');
        self::assertSelectorTextContains('.widget-commute .status', 'Trafic fluide');
        self::assertSelectorTextContains('.widget-commute', 'Arrivée vers 13:39');
        self::assertSelectorTextContains('.widget-commute', '9,6 km par Boulevard Périphérique Nord');
        self::assertSelectorTextNotContains('.widget-commute', 'Rue de Bonnel Lyon');
        self::assertSelectorNotExists('.unavailable');
    }

    public function testPreviewWithoutConfiguration(): void
    {
        $this->logIn();

        $this->client->request('GET', '/preview');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.widget-weather.unavailable', 'Aucun lieu météo configuré');
        self::assertSelectorTextContains('.widget-commute', 'Aucun trajet prévu à cette heure');
    }

    public function testInactiveTripIsNotShown(): void
    {
        $this->createCommute();
        $this->logIn();
        self::mockTime('2026-10-12 21:00:00');

        $this->client->request('GET', '/preview');

        self::assertSelectorTextContains('.widget-commute', 'Aucun trajet prévu à cette heure');
    }

    public function testWazeFailureOnlyAffectsTheCommuteWidget(): void
    {
        $this->createCommute();
        $this->logIn();
        $this->routingResponse = new MockResponse('', ['http_code' => 403]);

        $this->client->request('GET', '/preview');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.widget-commute.unavailable', 'Waze a refusé la requête (HTTP 403).');
        self::assertSelectorTextContains('.widget-weather .condition', 'Ciel dégagé');
    }

    public function testOpenMeteoFailureOnlyAffectsTheWeatherWidget(): void
    {
        $this->createCommute();
        $this->logIn();
        $this->weatherResponse = new MockResponse('', ['http_code' => 500]);

        $this->client->request('GET', '/preview');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.widget-weather.unavailable', 'Open-Meteo est injoignable');
        self::assertSelectorTextContains('.widget-commute h2', 'Maison → Travail');
    }

    public function testWeatherLocationWithoutCoordinatesIsReported(): void
    {
        $this->entityManager()->persist((new Place())->setLabel('Chalet')->setAddress('Quelque part')->setWeatherLocation(true));
        $this->entityManager()->flush();
        $this->logIn();

        $this->client->request('GET', '/preview');

        self::assertSelectorTextContains('.widget-weather.unavailable', 'Coordonnées manquantes pour le lieu météo Chalet');
    }

    public function testTripStartingFromAPlaceWithoutCoordinatesIsReported(): void
    {
        $trip = $this->createCommute();
        $trip->getOrigin()?->setLatitude(null);
        $this->entityManager()->flush();
        $this->logIn();

        $this->client->request('GET', '/preview');

        self::assertSelectorTextContains('.widget-commute.unavailable', 'Coordonnées manquantes pour le trajet Maison → Travail');
    }

    public function testTripWithoutCoordinatesIsReported(): void
    {
        $this->createCommute(withCoordinates: false);
        $this->logIn();

        $this->client->request('GET', '/preview');

        self::assertSelectorTextContains('.widget-commute.unavailable', 'Coordonnées manquantes pour le trajet Maison → Travail');
    }

    public function testPreviewOnAChosenDayAndTimeShowsTheWidgetsOfThatMoment(): void
    {
        $this->createCommute();
        $this->logIn();

        $this->client->request('GET', '/preview?day=2&time=21:30');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.date', 'Mardi 13 octobre');
        self::assertSelectorTextContains('.meta', 'Mis à jour à 21:30');
        self::assertSelectorTextContains('.widget-commute', 'Aucun trajet prévu à cette heure');
        // La météo reste celle du moment présent
        self::assertSelectorTextContains('.widget-weather .temp', '16°');

        $this->client->request('GET', '/preview?day=2&time=07:00');

        self::assertSelectorTextContains('.widget-commute h2', 'Maison → Travail');
        self::assertSelectorTextContains('.widget-commute', 'Arrivée vers 07:19');
    }

    public function testDayOrTimeAloneKeepsTheOtherOneCurrent(): void
    {
        $this->logIn();

        $this->client->request('GET', '/preview?time=07:00');
        self::assertSelectorTextContains('.date', 'Lundi 12 octobre');
        self::assertSelectorTextContains('.meta', 'Mis à jour à 07:00');

        $this->client->request('GET', '/preview?day=7');
        self::assertSelectorTextContains('.date', 'Dimanche 18 octobre');
        self::assertSelectorTextContains('.meta', 'Mis à jour à 13:20');
    }

    #[DataProvider('invalidMoments')]
    public function testInvalidMomentIsRejected(string $query): void
    {
        $this->logIn();

        $this->client->request('GET', '/preview?'.$query);

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidMoments(): iterable
    {
        yield 'jour inexistant' => ['day=8'];
        yield 'jour zéro' => ['day=0'];
        yield 'heure hors quart d\'heure' => ['time=08:10'];
        yield 'heure invalide' => ['time=25:00'];
    }

    public function testPreviewImageOnAChosenDayAndTime(): void
    {
        $this->logIn();

        $this->client->request('GET', '/preview.png?day=2&time=07:00');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
    }

    public function testPreviewImage(): void
    {
        $this->createCommute();
        $this->logIn();

        $this->client->request('GET', '/preview.png');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
    }
}

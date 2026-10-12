<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Admin\Field\QuarterHourField;
use App\Controller\Admin\DashboardController;
use App\Controller\Admin\DeviceCrudController;
use App\Controller\Admin\PlaceCrudController;
use App\Controller\Admin\TripCrudController;
use App\Entity\Place;
use App\EventSubscriber\GeocodePlaceSubscriber;
use App\Form\PreviewType;
use App\Repository\PlaceRepository;
use App\Repository\TripRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(DashboardController::class)]
#[CoversClass(PlaceCrudController::class)]
#[CoversClass(TripCrudController::class)]
#[CoversClass(DeviceCrudController::class)]
#[CoversClass(GeocodePlaceSubscriber::class)]
#[CoversClass(PreviewType::class)]
#[CoversClass(QuarterHourField::class)]
final class AdminTest extends FunctionalTestCase
{
    #[DataProvider('pages')]
    public function testPageIsDisplayed(string $url, string $title): void
    {
        $this->createCommute();
        $this->createDevice();
        $this->logIn();

        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $title);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pages(): iterable
    {
        yield 'aperçu' => ['/admin', 'Aperçu'];
        yield 'lieux' => ['/admin/place', 'Lieux'];
        yield 'nouveau lieu' => ['/admin/place/new', 'Ajouter un lieu'];
        yield 'trajets' => ['/admin/trip', 'Trajets'];
        yield 'nouveau trajet' => ['/admin/trip/new', 'Ajouter un trajet'];
        yield 'liseuses' => ['/admin/device', 'Liseuses'];
        yield 'nouvelle liseuse' => ['/admin/device/new', 'Ajouter une liseuse'];
    }

    public function testPreviewOnAChosenDayAndTime(): void
    {
        $this->logIn();

        $this->client->request('GET', '/admin');
        self::assertSelectorTextContains('body', 'telle que les liseuses la reçoivent');
        self::assertSelectorExists('img[src="/preview.png"]');
        self::assertSelectorCount(96 + 1, 'select[name="time"] option');

        $this->client->submitForm('Prévisualiser', ['day' => '2', 'time' => '21:30'], 'GET');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'recevraient mardi à 21:30');
        self::assertSelectorExists('img[src="/preview.png?day=2&time=21:30"]');
        self::assertSelectorExists('a[href="/preview?day=2&time=21:30"]');
        self::assertSelectorExists('select[name="day"] option[value="2"][selected]');
        self::assertSelectorExists('select[name="time"] option[value="21:30"][selected]');
    }

    public function testInvalidPreviewFallsBackToTheCurrentMoment(): void
    {
        $this->logIn();

        $this->client->request('GET', '/admin?day=9&time=21:30&preview=');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('select[name="day"].is-invalid');
        self::assertSelectorTextContains('body', 'telle que les liseuses la reçoivent');
        self::assertSelectorExists('img[src="/preview.png"]');
    }

    public function testTripHoursAreChosenByQuarterHour(): void
    {
        $trip = $this->createCommute();
        $this->logIn();

        $crawler = $this->client->request('GET', \sprintf('/admin/trip/%d/edit', (int) $trip->getId()));
        self::assertSelectorExists('select[name="Trip[startTime]"] option[value="06:00"][selected]');
        self::assertSelectorCount(96, 'select[name="Trip[endTime]"] option');

        $form = $crawler->selectButton('Sauvegarder les modifications')->form([
            'Trip[startTime]' => '07:15',
            'Trip[endTime]' => '09:45',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $saved = static::getContainer()->get(TripRepository::class)->find($trip->getId());
        self::assertNotNull($saved);
        self::assertSame('07:15', $saved->getStartTime()?->format('H:i'));
        self::assertSame('09:45', $saved->getEndTime()?->format('H:i'));

        $this->client->request('GET', '/admin/trip');
        self::assertSelectorTextSame('td[data-column="startTime"]', '07:15');
    }

    public function testDeviceListShowsWhetherAKeyExists(): void
    {
        $this->createDevice();
        $this->logIn();

        $this->client->request('GET', '/admin/device');

        self::assertSelectorTextSame('td[data-column="hasToken"] .badge', 'Oui');
        self::assertSelectorNotExists('td[data-column="hasToken"] input');
    }

    public function testPlaceListShowsPreciseCoordinates(): void
    {
        $this->createCommute();
        $this->logIn();

        $this->client->request('GET', '/admin/place');

        self::assertSelectorTextSame('td[data-column="latitude"]', '45,757600');
        self::assertSelectorTextSame('td[data-column="longitude"]', '4,832000');
    }

    public function testNewPlaceIsGeocodedFromItsAddress(): void
    {
        $this->logIn();

        $this->client->request('GET', '/admin/place/new');
        $this->client->submitForm('Créer', [
            'Place[label]' => 'Maison',
            'Place[address]' => 'Place Bellecour, Lyon',
        ]);

        self::assertResponseRedirects();
        $place = $this->findPlace('Maison');
        self::assertSame(45.75762, $place->getLatitude());
        self::assertSame(4.8320198, $place->getLongitude());
    }

    public function testManualCoordinatesAreKept(): void
    {
        $this->logIn();

        $this->client->request('GET', '/admin/place/new');
        $this->client->submitForm('Créer', [
            'Place[label]' => 'Chalet',
            'Place[address]' => 'Quelque part',
            'Place[latitude]' => '45.1',
            'Place[longitude]' => '6.2',
        ]);

        $place = $this->findPlace('Chalet');
        self::assertSame(45.1, $place->getLatitude());
        self::assertSame(6.2, $place->getLongitude());
    }

    public function testUnknownAddressIsReportedToTheUser(): void
    {
        $this->logIn();
        $this->autocompleteResponse = new MockResponse('["nulle part",[]]');

        $this->client->request('GET', '/admin/place/new');
        $this->client->submitForm('Créer', [
            'Place[label]' => 'Nulle part',
            'Place[address]' => 'Nulle part',
        ]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Adresse introuvable : "Nulle part". Renseigne la latitude et la longitude à la main.');
        self::assertNull($this->findPlace('Nulle part')->getCoordinates());
    }

    private function findPlace(string $label): Place
    {
        $place = static::getContainer()->get(PlaceRepository::class)->findOneBy(['label' => $label]);
        self::assertInstanceOf(Place::class, $place);

        return $place;
    }
}

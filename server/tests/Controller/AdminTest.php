<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\DeviceCrudController;
use App\Controller\Admin\PlaceCrudController;
use App\Controller\Admin\TripCrudController;
use App\Entity\Place;
use App\EventSubscriber\GeocodePlaceSubscriber;
use App\Form\PreviewType;
use App\Repository\PlaceRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(DashboardController::class)]
#[CoversClass(PlaceCrudController::class)]
#[CoversClass(TripCrudController::class)]
#[CoversClass(DeviceCrudController::class)]
#[CoversClass(GeocodePlaceSubscriber::class)]
#[CoversClass(PreviewType::class)]
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

    public function testPreviewAtAChosenMoment(): void
    {
        $this->logIn();

        $this->client->request('GET', '/admin');
        self::assertSelectorTextContains('body', 'telle que les liseuses la reçoivent');
        self::assertSelectorExists('img[src="/preview.png"]');

        $this->client->submitForm('Prévisualiser', ['at' => '2026-10-13T21:30'], 'GET');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'recevraient le mardi 13 octobre à 21:30');
        self::assertSelectorExists('img[src="/preview.png?at=2026-10-13T21:30"]');
        self::assertSelectorExists('a[href="/preview?at=2026-10-13T21:30"]');
        self::assertInputValueSame('at', '2026-10-13T21:30');
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

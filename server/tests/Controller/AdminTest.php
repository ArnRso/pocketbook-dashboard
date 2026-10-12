<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\DashboardCrudController;
use App\Controller\Admin\DeviceCrudController;
use App\Controller\Admin\PlaceCrudController;
use App\Controller\Admin\ReminderCrudController;
use App\Controller\Admin\TripCrudController;
use App\Entity\Place;
use App\Entity\Reminder;
use App\EventSubscriber\GeocodePlaceSubscriber;
use App\Form\PreviewType;
use App\Form\ReminderWindowType;
use App\Repository\DeviceRepository;
use App\Repository\PlaceRepository;
use App\Repository\ReminderRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(DashboardController::class)]
#[CoversClass(DashboardCrudController::class)]
#[CoversClass(PlaceCrudController::class)]
#[CoversClass(TripCrudController::class)]
#[CoversClass(ReminderCrudController::class)]
#[CoversClass(ReminderWindowType::class)]
#[CoversClass(DeviceCrudController::class)]
#[CoversClass(GeocodePlaceSubscriber::class)]
#[CoversClass(PreviewType::class)]
final class AdminTest extends FunctionalTestCase
{
    #[DataProvider('pages')]
    public function testPageIsDisplayed(string $url, string $title): void
    {
        $this->createDevice(dashboard: $this->createDashboard());
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
        yield 'dashboards' => ['/admin/dashboard', 'Dashboards'];
        yield 'nouveau dashboard' => ['/admin/dashboard/new', 'Ajouter un dashboard'];
        yield 'lieux' => ['/admin/place', 'Lieux'];
        yield 'nouveau lieu' => ['/admin/place/new', 'Ajouter un lieu'];
        yield 'trajets' => ['/admin/trip', 'Trajets'];
        yield 'nouveau trajet' => ['/admin/trip/new', 'Ajouter un trajet'];
        yield 'rappels' => ['/admin/reminder', 'Rappels'];
        yield 'nouveau rappel' => ['/admin/reminder/new', 'Ajouter un rappel'];
        yield 'liseuses' => ['/admin/device', 'Liseuses'];
        yield 'nouvelle liseuse' => ['/admin/device/new', 'Ajouter une liseuse'];
    }

    public function testPreviewOfAChosenDashboardDayAndTime(): void
    {
        $id = (int) $this->createDashboard()->getId();
        $this->logIn();

        $this->client->request('GET', '/admin');
        self::assertSelectorTextContains('body', 'Voici « Principal » tel que les liseuses le reçoivent');
        self::assertSelectorExists(\sprintf('img[src="/preview.png?dashboard=%d"]', $id));
        self::assertSelectorCount(96 + 1, 'select[name="time"] option');

        $this->client->submitForm('Prévisualiser', ['dashboard' => (string) $id, 'day' => '2', 'time' => '21:30'], 'GET');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'recevraient mardi à 21:30');
        self::assertSelectorExists(\sprintf('img[src="/preview.png?dashboard=%d&day=2&time=21:30"]', $id));
        self::assertSelectorExists(\sprintf('a[href="/preview?dashboard=%d&day=2&time=21:30"]', $id));
        self::assertSelectorExists('select[name="day"] option[value="2"][selected]');
        self::assertSelectorExists('select[name="time"] option[value="21:30"][selected]');
    }

    public function testInvalidPreviewFallsBackToTheCurrentMoment(): void
    {
        $this->createDashboard();
        $this->logIn();

        $this->client->request('GET', '/admin?day=9&time=21:30&preview=');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('select[name="day"].is-invalid');
        self::assertSelectorTextContains('body', 'tel que les liseuses le reçoivent');
    }

    public function testPreviewWithoutDashboard(): void
    {
        $this->logIn();

        $this->client->request('GET', '/admin');

        self::assertSelectorTextContains('body', 'Aucun dashboard pour l\'instant');
        self::assertSelectorNotExists('img[alt="Aperçu du dashboard"]');
    }

    public function testDashboardIsAssignedToADevice(): void
    {
        $dashboard = $this->createDashboard();
        [$device] = $this->createDevice();
        $this->logIn();

        $this->client->request('GET', \sprintf('/admin/device/%d/edit', (int) $device->getId()));
        $this->client->submitForm('Sauvegarder les modifications', ['Device[dashboard]' => (string) $dashboard->getId()]);

        self::assertResponseRedirects();
        self::assertSame('Principal', static::getContainer()->get(DeviceRepository::class)->find($device->getId())?->getDashboard()?->getName());
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
        $this->createDashboard();
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

    public function testReminderIsCreatedWithWindowsSpanningSeveralDays(): void
    {
        $this->logIn();

        $crawler = $this->client->request('GET', '/admin/reminder/new');
        // Les plages sont ajoutées en JavaScript dans le navigateur : on les envoie directement
        $this->client->request('POST', $crawler->selectButton('Créer')->form()->getUri(), ['Reminder' => [
            'text' => 'Sortir la poubelle verte',
            'windows' => [
                ['startDay' => '7', 'startTime' => '16:00', 'endDay' => '1', 'endTime' => '09:00'],
                ['startDay' => '3', 'startTime' => '18:00', 'endDay' => '3', 'endTime' => '20:30'],
            ],
            'enabled' => '1',
            '_token' => $crawler->filter('input[name="Reminder[_token]"]')->attr('value'),
        ]]);

        self::assertResponseRedirects();
        $reminder = static::getContainer()->get(ReminderRepository::class)->findOneBy(['text' => 'Sortir la poubelle verte']);
        self::assertInstanceOf(Reminder::class, $reminder);
        self::assertSame(['Dim 16:00 → Lun 09:00', 'Mer 18:00 → Mer 20:30'], array_map(strval(...), $reminder->getWindows()->toArray()));
        self::assertTrue($reminder->isEnabled());

        $this->client->request('GET', '/admin/reminder');

        self::assertSelectorTextSame('td[data-column="windows"]', 'Dim 16:00 → Lun 09:00, Mer 18:00 → Mer 20:30');
    }

    public function testReminderWithoutWindowIsRejected(): void
    {
        $this->logIn();

        $this->client->request('GET', '/admin/reminder/new');
        $this->client->submitForm('Créer', ['Reminder[text]' => 'Sortir la poubelle verte']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Ajoute au moins une plage.');
    }

    private function findPlace(string $label): Place
    {
        $place = static::getContainer()->get(PlaceRepository::class)->findOneBy(['label' => $label]);
        self::assertInstanceOf(Place::class, $place);

        return $place;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Widget;

use App\Entity\Place;
use App\Entity\Trip;
use App\Geo\Coordinates;
use App\Repository\PlaceRepository;
use App\Repository\TripRepository;
use App\Traffic\Route;
use App\Traffic\TravelTimeProviderInterface;
use App\Weather\OpenMeteoClient;
use App\Widget\CacheKey;
use App\Widget\CommuteWidget;
use App\Widget\WeatherWidget;
use App\Widget\WidgetNotConfiguredException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\SerializerInterface;

#[CoversClass(WeatherWidget::class)]
#[CoversClass(CommuteWidget::class)]
#[CoversClass(CacheKey::class)]
final class WidgetCacheTest extends KernelTestCase
{
    public function testForecastIsFetchedOncePerCacheLifetime(): void
    {
        $this->persist((new Place())->setLabel('Maison')->setAddress('Lyon')->setLatitude(45.7576)->setLongitude(4.832)->setWeatherLocation(true));
        $json = file_get_contents(__DIR__.'/../fixtures/open-meteo-forecast.json');
        self::assertIsString($json);
        $http = new MockHttpClient([new MockResponse($json)], 'https://api.open-meteo.com');
        $clock = new MockClock('2026-10-12 13:20:00', 'Europe/Paris');
        $widget = new WeatherWidget(
            self::getContainer()->get(PlaceRepository::class),
            new OpenMeteoClient($http, self::getContainer()->get(SerializerInterface::class), $clock),
            new ArrayAdapter(),
        );

        $first = $widget->data();
        $second = $widget->data();

        self::assertSame(1, $http->getRequestsCount());
        self::assertSame('Maison', $second->place);
        self::assertEquals($first->report, $second->report);
    }

    public function testRoutesAreFetchedOncePerCacheLifetimeAndTrip(): void
    {
        $home = $this->persist((new Place())->setLabel('Maison')->setAddress('Lyon')->setLatitude(45.7576)->setLongitude(4.832));
        $work = $this->persist((new Place())->setLabel('Travail')->setAddress('Villeurbanne')->setLatitude(45.7719)->setLongitude(4.8902));
        $trip = $this->persist((new Trip())->setOrigin($home)->setDestination($work)
            ->setStartTime(new \DateTimeImmutable('06:00'))->setEndTime(new \DateTimeImmutable('20:00')));

        $travelTime = $this->createMock(TravelTimeProviderInterface::class);
        $travelTime->expects(self::exactly(2))
            ->method('routes')
            ->with(new Coordinates(45.7576, 4.832), new Coordinates(45.7719, 4.8902), self::isBool())
            ->willReturn([new Route('A7', 19, 18, 9.6)]);
        $widget = new CommuteWidget(
            self::getContainer()->get(TripRepository::class),
            $travelTime,
            new ArrayAdapter(),
            new MockClock('2026-10-12 08:00:00', 'Europe/Paris'),
        );

        $commute = $widget->data()->commute;
        $widget->data();
        // Changer l'option péages change d'itinéraire : nouvel appel
        $trip->setAvoidTolls(true);
        $widget->data();

        self::assertNotNull($commute);
        self::assertSame('Maison', $commute->origin);
        self::assertSame('Travail', $commute->destination);
        self::assertSame('08:19', $commute->arrival()->format('H:i'));
    }

    public function testTripWithoutDestinationIsReported(): void
    {
        $origin = (new Place())->setLabel('Maison')->setAddress('Lyon')->setLatitude(45.7576)->setLongitude(4.832);
        $trip = (new Trip())->setOrigin($origin)
            ->setStartTime(new \DateTimeImmutable('06:00'))->setEndTime(new \DateTimeImmutable('20:00'));
        $trips = self::createStub(TripRepository::class);
        $trips->method('findActiveAt')->willReturn($trip);
        $widget = new CommuteWidget($trips, self::createStub(TravelTimeProviderInterface::class), new ArrayAdapter(), new MockClock('2026-10-12 08:00:00'));

        $this->expectException(WidgetNotConfiguredException::class);
        $this->expectExceptionMessageIs('Coordonnées manquantes pour le trajet Maison → ');

        $widget->data();
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function persist(object $entity): object
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();

        return $entity;
    }
}

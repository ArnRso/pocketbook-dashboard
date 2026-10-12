<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Place;
use App\Entity\Trip;
use App\Repository\PlaceRepository;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(TripRepository::class)]
#[CoversClass(PlaceRepository::class)]
final class RepositoryTest extends KernelTestCase
{
    public function testEarliestOverlappingTripWins(): void
    {
        [$home, $work] = [$this->place('Maison'), $this->place('Travail')];
        // Le trajet qui commence le plus tard est enregistré en premier
        $this->persist($this->trip($work, $home, '08:30', '12:00'));
        $this->persist($this->trip($home, $work, '07:00', '10:00'));

        $trip = self::getContainer()->get(TripRepository::class)->findActiveAt(new \DateTimeImmutable('2026-10-12 09:00'));

        self::assertSame('Maison → Travail', (string) $trip);
    }

    public function testDisabledTripsAreIgnored(): void
    {
        [$home, $work] = [$this->place('Maison'), $this->place('Travail')];
        $this->persist($this->trip($home, $work, '07:00', '10:00')->setEnabled(false));

        self::assertNull(self::getContainer()->get(TripRepository::class)->findActiveAt(new \DateTimeImmutable('2026-10-12 09:00')));
    }

    public function testFirstWeatherLocationWins(): void
    {
        $this->place('Bureau');
        $this->place('Maison')->setWeatherLocation(true);
        $this->place('Chalet')->setWeatherLocation(true);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        self::assertSame('Maison', self::getContainer()->get(PlaceRepository::class)->findWeatherLocation()?->getLabel());
    }

    private function place(string $label): Place
    {
        return $this->persist((new Place())->setLabel($label)->setAddress($label));
    }

    private function trip(Place $origin, Place $destination, string $start, string $end): Trip
    {
        return (new Trip())
            ->setOrigin($origin)
            ->setDestination($destination)
            ->setStartTime(new \DateTimeImmutable($start))
            ->setEndTime(new \DateTimeImmutable($end));
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

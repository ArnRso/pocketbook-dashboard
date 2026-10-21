<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Place;
use App\Entity\Trip;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(Trip::class)]
final class TripValidationTest extends KernelTestCase
{
    public function testValidTrip(): void
    {
        self::assertCount(0, $this->validate($this->trip(new Place(), new Place())));
    }

    public function testDestinationMustDifferFromOrigin(): void
    {
        $place = new Place();

        $violations = $this->validate($this->trip($place, $place));

        self::assertCount(1, $violations);
        self::assertSame('destination', $violations->get(0)->getPropertyPath());
        self::assertSame('La destination doit être différente du départ.', $violations->get(0)->getMessage());
    }

    public function testPlacesAreRequired(): void
    {
        $violations = $this->validate(new Trip());

        self::assertCount(2, $violations);
        self::assertSame('origin', $violations->get(0)->getPropertyPath());
        self::assertSame('destination', $violations->get(1)->getPropertyPath());
    }

    public function testDescription(): void
    {
        $trip = $this->trip(new Place()->setLabel('Maison'), new Place()->setLabel('Travail'));

        self::assertSame('Maison → Travail', (string) $trip);
    }

    private function trip(Place $origin, Place $destination): Trip
    {
        return new Trip()->setOrigin($origin)->setDestination($destination);
    }

    private function validate(Trip $trip): ConstraintViolationListInterface
    {
        return self::getContainer()->get(ValidatorInterface::class)->validate($trip);
    }
}

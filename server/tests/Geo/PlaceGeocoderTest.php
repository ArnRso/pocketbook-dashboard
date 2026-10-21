<?php

declare(strict_types=1);

namespace App\Tests\Geo;

use App\Entity\Place;
use App\Geo\Coordinates;
use App\Geo\GeocoderInterface;
use App\Geo\GeocodingException;
use App\Geo\PlaceGeocoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlaceGeocoder::class)]
#[CoversClass(Place::class)]
final class PlaceGeocoderTest extends TestCase
{
    public function testMissingCoordinatesAreComputedFromTheAddress(): void
    {
        $geocoder = $this->createMock(GeocoderInterface::class);
        $geocoder->expects(self::once())->method('geocode')->with('Place Bellecour, Lyon')->willReturn(new Coordinates(45.7576, 4.832));
        $place = new Place()->setAddress('Place Bellecour, Lyon');

        new PlaceGeocoder($geocoder)->completeCoordinates($place);

        self::assertSame(45.7576, $place->getLatitude());
        self::assertSame(4.832, $place->getLongitude());
    }

    public function testExistingCoordinatesAreKept(): void
    {
        $geocoder = $this->createMock(GeocoderInterface::class);
        $geocoder->expects(self::never())->method('geocode');
        $place = new Place()->setAddress('Place Bellecour, Lyon')->setLatitude(1.0)->setLongitude(2.0);

        new PlaceGeocoder($geocoder)->completeCoordinates($place);

        self::assertEquals(new Coordinates(1.0, 2.0), $place->getCoordinates());
    }

    public function testPartialCoordinatesAreRecomputed(): void
    {
        $geocoder = self::createStub(GeocoderInterface::class);
        $geocoder->method('geocode')->willReturn(new Coordinates(45.7576, 4.832));
        $place = new Place()->setAddress('Place Bellecour, Lyon')->setLatitude(1.0);

        new PlaceGeocoder($geocoder)->completeCoordinates($place);

        self::assertEquals(new Coordinates(45.7576, 4.832), $place->getCoordinates());
    }

    public function testGeocodingFailureIsPropagated(): void
    {
        $geocoder = self::createStub(GeocoderInterface::class);
        $geocoder->method('geocode')->willThrowException(new GeocodingException('Adresse introuvable'));

        $this->expectException(GeocodingException::class);

        new PlaceGeocoder($geocoder)->completeCoordinates(new Place()->setAddress('Nulle part'));
    }
}

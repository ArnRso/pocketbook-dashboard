<?php

declare(strict_types=1);

namespace App\Geo;

interface GeocoderInterface
{
    /**
     * @param Coordinates|null $near point de référence pour départager les adresses ambiguës
     *
     * @throws GeocodingException
     */
    public function geocode(string $address, ?Coordinates $near = null): Coordinates;
}

<?php

declare(strict_types=1);

namespace App\Geo;

use App\Entity\Place;

class PlaceGeocoder
{
    public function __construct(
        private readonly GeocoderInterface $geocoder,
    ) {
    }

    /**
     * Calcule les coordonnées du lieu à partir de son adresse si elles ne sont pas renseignées.
     *
     * @throws GeocodingException
     */
    public function completeCoordinates(Place $place): void
    {
        if (null !== $place->getCoordinates()) {
            return;
        }

        $place->setCoordinates($this->geocoder->geocode($place->getAddress()));
    }
}

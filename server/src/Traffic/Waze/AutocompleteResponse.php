<?php

declare(strict_types=1);

namespace App\Traffic\Waze;

use App\Geo\Coordinates;

/**
 * Suggestions de la recherche d'adresse de Waze (voir AutocompleteResponseDenormalizer).
 */
final readonly class AutocompleteResponse
{
    /**
     * @param list<AutocompletePlace> $places
     */
    public function __construct(
        public array $places,
    ) {
    }

    /**
     * Coordonnées de la première suggestion exploitable (hors publicités).
     */
    public function firstCoordinates(): ?Coordinates
    {
        foreach ($this->places as $place) {
            $coordinates = $place->coordinates();
            if (null !== $coordinates && !$place->isAdvertisement()) {
                return $coordinates;
            }
        }

        return null;
    }
}

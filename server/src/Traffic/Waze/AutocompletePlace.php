<?php

declare(strict_types=1);

namespace App\Traffic\Waze;

use App\Geo\Coordinates;
use Symfony\Component\Serializer\Attribute\SerializedName;

final readonly class AutocompletePlace
{
    public function __construct(
        #[SerializedName('v')]
        public string $id = '',
        #[SerializedName('y')]
        public ?float $latitude = null,
        #[SerializedName('x')]
        public ?float $longitude = null,
    ) {
    }

    public function isAdvertisement(): bool
    {
        return str_starts_with($this->id, 'advertisement.');
    }

    public function coordinates(): ?Coordinates
    {
        if (null === $this->latitude || null === $this->longitude) {
            return null;
        }

        return new Coordinates($this->latitude, $this->longitude);
    }
}

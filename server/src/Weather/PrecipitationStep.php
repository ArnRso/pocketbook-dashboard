<?php

declare(strict_types=1);

namespace App\Weather;

/**
 * Cumul de pluie prévu sur un quart d'heure.
 */
final readonly class PrecipitationStep
{
    public function __construct(
        public \DateTimeImmutable $time,
        public float $millimeters,
    ) {
    }

    public function isRaining(): bool
    {
        return $this->millimeters > 0;
    }
}

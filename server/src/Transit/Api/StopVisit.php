<?php

declare(strict_types=1);

namespace App\Transit\Api;

use Symfony\Component\Serializer\Attribute\SerializedPath;

final readonly class StopVisit
{
    public function __construct(
        #[SerializedPath('[MonitoredVehicleJourney][LineRef][value]')]
        public string $line,
        #[SerializedPath('[MonitoredVehicleJourney][DirectionRef][value]')]
        public string $direction,
        #[SerializedPath('[MonitoredVehicleJourney][MonitoredCall][AimedDepartureTime]')]
        public \DateTimeImmutable $aimed,
        /** Heure estimée en temps réel, absente pour un passage seulement théorique */
        #[SerializedPath('[MonitoredVehicleJourney][MonitoredCall][ExpectedDepartureTime]')]
        public ?\DateTimeImmutable $expected = null,
    ) {
    }
}

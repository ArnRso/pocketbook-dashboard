<?php

declare(strict_types=1);

namespace App\Transit\Api;

use Symfony\Component\Serializer\Attribute\SerializedPath;

/**
 * Réponse de stop-monitoring : les prochains passages à un arrêt, toutes lignes confondues.
 */
final readonly class StopMonitoringResponse
{
    /**
     * @param list<StopVisit> $visits
     */
    public function __construct(
        #[SerializedPath('[Siri][ServiceDelivery][StopMonitoringDelivery][0][MonitoredStopVisit]')]
        public array $visits = [],
    ) {
    }
}

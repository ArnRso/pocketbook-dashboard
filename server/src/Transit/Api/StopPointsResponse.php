<?php

declare(strict_types=1);

namespace App\Transit\Api;

use Symfony\Component\Serializer\Attribute\SerializedPath;

/**
 * Réponse de stoppoints-discovery : les points d'arrêt (un par quai), regroupés en zones d'arrêt.
 */
final readonly class StopPointsResponse
{
    /**
     * @param list<AnnotatedStopPoint> $stopPoints
     */
    public function __construct(
        #[SerializedPath('[Siri][StopPointsDelivery][AnnotatedStopPointRef]')]
        public array $stopPoints,
    ) {
    }
}

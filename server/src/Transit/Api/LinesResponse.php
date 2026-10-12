<?php

declare(strict_types=1);

namespace App\Transit\Api;

use Symfony\Component\Serializer\Attribute\SerializedPath;

/**
 * Réponse de lines-discovery : les lignes du réseau.
 */
final readonly class LinesResponse
{
    /**
     * @param list<AnnotatedLine> $lines
     */
    public function __construct(
        #[SerializedPath('[Siri][LinesDelivery][AnnotatedLineRef]')]
        public array $lines,
    ) {
    }
}

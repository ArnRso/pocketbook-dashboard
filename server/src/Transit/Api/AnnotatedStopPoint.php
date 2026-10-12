<?php

declare(strict_types=1);

namespace App\Transit\Api;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;

final readonly class AnnotatedStopPoint
{
    /**
     * @param list<Reference> $lines
     */
    public function __construct(
        #[SerializedPath('[StopName][value]')]
        public string $name,
        /** Zone d'arrêt : absente pour les points qui ne sont plus desservis */
        #[SerializedPath('[StopAreaRef][value]')]
        public ?string $area = null,
        #[SerializedName('Lines')]
        public array $lines = [],
    ) {
    }
}

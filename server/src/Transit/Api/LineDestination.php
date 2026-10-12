<?php

declare(strict_types=1);

namespace App\Transit\Api;

use Symfony\Component\Serializer\Attribute\SerializedPath;

final readonly class LineDestination
{
    public function __construct(
        #[SerializedPath('[DirectionRef][value]')]
        public string $direction,
        #[SerializedPath('[PlaceName][0][value]')]
        public string $name,
    ) {
    }
}

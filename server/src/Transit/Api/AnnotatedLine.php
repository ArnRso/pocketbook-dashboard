<?php

declare(strict_types=1);

namespace App\Transit\Api;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;

final readonly class AnnotatedLine
{
    /**
     * @param list<LineDestination> $destinations
     */
    public function __construct(
        #[SerializedPath('[LineRef][value]')]
        public string $ref,
        #[SerializedPath('[LineCode][value]')]
        public string $code,
        #[SerializedPath('[LineName][0][value]')]
        public string $name,
        #[SerializedName('Destinations')]
        public array $destinations = [],
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Transit;

final readonly class TransitLine
{
    /**
     * @param array<array-key, string> $destinations terminus par direction (« 0 », « 1 », que PHP range en clés entières)
     */
    public function __construct(
        public string $ref,
        public string $code,
        public string $name,
        public array $destinations,
    ) {
    }
}

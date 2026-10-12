<?php

declare(strict_types=1);

namespace App\Traffic\Waze;

final readonly class RouteSegment
{
    public function __construct(
        public int $crossTime,
        public int $crossTimeWithoutRealTime,
        public int $length,
    ) {
    }
}

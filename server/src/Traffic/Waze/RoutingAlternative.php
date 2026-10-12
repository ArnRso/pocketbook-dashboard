<?php

declare(strict_types=1);

namespace App\Traffic\Waze;

final readonly class RoutingAlternative
{
    public function __construct(
        public RouteResult $response,
    ) {
    }
}

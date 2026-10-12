<?php

declare(strict_types=1);

namespace App\Widget;

use App\Weather\WeatherReport;

final readonly class WeatherView
{
    public function __construct(
        public string $place,
        public WeatherReport $report,
    ) {
    }
}

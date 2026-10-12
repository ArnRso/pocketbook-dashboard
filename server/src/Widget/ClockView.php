<?php

declare(strict_types=1);

namespace App\Widget;

final readonly class ClockView
{
    public function __construct(
        public \DateTimeImmutable $moment,
    ) {
    }
}

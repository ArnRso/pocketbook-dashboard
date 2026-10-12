<?php

declare(strict_types=1);

namespace App\Widget;

final readonly class ActiveReminder
{
    public function __construct(
        public string $text,
        public \DateTimeImmutable $until,
    ) {
    }
}

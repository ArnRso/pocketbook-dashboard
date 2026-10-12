<?php

declare(strict_types=1);

namespace App\Chaban;

final readonly class BridgeClosure
{
    public function __construct(
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        /** Bateau qui passe, ou « Maintenance » */
        public string $reason,
        public bool $total,
    ) {
    }

    public function isOngoingAt(\DateTimeImmutable $moment): bool
    {
        return $this->start <= $moment && $moment < $this->end;
    }

    public function overlaps(\DateTimeImmutable $from, \DateTimeImmutable $to): bool
    {
        return $this->start < $to && $from < $this->end;
    }
}

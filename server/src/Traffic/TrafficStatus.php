<?php

declare(strict_types=1);

namespace App\Traffic;

enum TrafficStatus: string
{
    case Fluid = 'fluid';
    case Slow = 'slow';
    case Jammed = 'jammed';

    public static function fromDelay(float $minutes): self
    {
        return match (true) {
            $minutes < 3 => self::Fluid,
            $minutes < 10 => self::Slow,
            default => self::Jammed,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Fluid => 'Trafic fluide',
            self::Slow => 'Ralentissements',
            self::Jammed => 'Bouchons',
        };
    }
}

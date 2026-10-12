<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Weekday;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Weekday::class)]
final class WeekdayTest extends TestCase
{
    public function testChoicesFollowIsoNumbering(): void
    {
        self::assertSame([
            'Lundi' => 1,
            'Mardi' => 2,
            'Mercredi' => 3,
            'Jeudi' => 4,
            'Vendredi' => 5,
            'Samedi' => 6,
            'Dimanche' => 7,
        ], Weekday::choices());
    }

    public function testNumberingMatchesPhpDates(): void
    {
        self::assertSame(Weekday::Monday, Weekday::from((int) (new \DateTimeImmutable('2026-10-12'))->format('N')));
        self::assertSame(Weekday::Sunday, Weekday::from((int) (new \DateTimeImmutable('2026-10-18'))->format('N')));
    }
}

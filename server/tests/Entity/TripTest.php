<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Trip;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Trip::class)]
final class TripTest extends TestCase
{
    #[DataProvider('moments')]
    public function testIsActiveAt(string $moment, bool $expected): void
    {
        $trip = (new Trip())
            ->setStartTime(new \DateTimeImmutable('06:00'))
            ->setEndTime(new \DateTimeImmutable('10:00'))
            ->setWeekdays([1, 2, 3, 4, 5]);

        self::assertSame($expected, $trip->isActiveAt(new \DateTimeImmutable($moment)));
    }

    public static function moments(): iterable
    {
        yield 'lundi pendant la plage' => ['2026-10-12 07:30', true];
        yield 'début inclus' => ['2026-10-12 06:00', true];
        yield 'fin exclue' => ['2026-10-12 10:00', false];
        yield 'avant la plage' => ['2026-10-12 05:59', false];
        yield 'samedi' => ['2026-10-17 07:30', false];
    }

    public function testIncompleteTripIsNeverActive(): void
    {
        $withoutEnd = (new Trip())->setStartTime(new \DateTimeImmutable('00:00'))->setWeekdays([1]);
        $withoutStart = (new Trip())->setEndTime(new \DateTimeImmutable('23:59'))->setWeekdays([1]);

        self::assertFalse($withoutEnd->isActiveAt(new \DateTimeImmutable('2026-10-12 12:00')));
        self::assertFalse($withoutStart->isActiveAt(new \DateTimeImmutable('2026-10-12 12:00')));
    }

    public function testDisabledTripIsNeverActive(): void
    {
        $trip = (new Trip())
            ->setStartTime(new \DateTimeImmutable('00:00'))
            ->setEndTime(new \DateTimeImmutable('23:59'))
            ->setWeekdays([1, 2, 3, 4, 5, 6, 7])
            ->setEnabled(false);

        self::assertFalse($trip->isActiveAt(new \DateTimeImmutable('2026-10-12 12:00')));
    }
}

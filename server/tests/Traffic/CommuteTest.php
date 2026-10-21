<?php

declare(strict_types=1);

namespace App\Tests\Traffic;

use App\Traffic\Commute;
use App\Traffic\Route;
use App\Traffic\TrafficStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Commute::class)]
#[CoversClass(Route::class)]
#[CoversClass(TrafficStatus::class)]
final class CommuteTest extends TestCase
{
    public function testArrivalUsesTheFastestRoute(): void
    {
        $commute = new Commute('Maison', 'Travail', [
            new Route('A', 19.4, 18.7, 9.6),
            new Route('B', 26.0, 24.3, 6.4),
        ], new \DateTimeImmutable('2026-10-12 08:00'));

        self::assertSame('A', $commute->best()->name);
        self::assertSame('08:19', $commute->arrival()->format('H:i'));
    }

    public function testArrivalIsRoundedToTheNearestMinute(): void
    {
        $commute = new Commute('Maison', 'Travail', [new Route('A', 19.6, 18.7, 9.6)], new \DateTimeImmutable('2026-10-12 08:00'));

        self::assertSame('08:20', $commute->arrival()->format('H:i'));
    }

    #[DataProvider('delays')]
    public function testStatusFromDelay(float $delay, TrafficStatus $expected): void
    {
        self::assertSame($expected, TrafficStatus::fromDelay($delay));
    }

    public static function delays(): iterable
    {
        yield [0, TrafficStatus::Fluid];
        yield [2.9, TrafficStatus::Fluid];
        yield [3, TrafficStatus::Slow];
        yield [9.9, TrafficStatus::Slow];
        yield [10, TrafficStatus::Jammed];
    }

    public function testDelayIsNeverNegative(): void
    {
        self::assertSame(0.0, new Route('A', 17, 18, 9)->delay());
    }
}

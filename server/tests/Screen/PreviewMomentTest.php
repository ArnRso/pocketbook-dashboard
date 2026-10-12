<?php

declare(strict_types=1);

namespace App\Tests\Screen;

use App\Screen\PreviewMoment;
use App\Screen\PreviewQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(PreviewMoment::class)]
#[CoversClass(PreviewQuery::class)]
final class PreviewMomentTest extends TestCase
{
    public function testNothingChosenMeansNow(): void
    {
        self::assertNull((new PreviewMoment(new MockClock('2026-10-14 10:00')))->resolve(new PreviewQuery()));
    }

    #[DataProvider('moments')]
    public function testChosenDayIsTakenInTheCurrentWeek(string $now, ?int $day, ?string $time, string $expected): void
    {
        $query = new PreviewQuery();
        $query->day = $day;
        $query->time = $time;

        $moment = (new PreviewMoment(new MockClock($now, 'Europe/Paris')))->resolve($query);

        self::assertNotNull($moment);
        self::assertSame($expected, $moment->format('D Y-m-d H:i'));
        self::assertSame('Europe/Paris', $moment->getTimezone()->getName());
    }

    /**
     * @return iterable<string, array{string, ?int, ?string, string}>
     */
    public static function moments(): iterable
    {
        yield 'lundi depuis un mercredi' => ['2026-10-14 10:00', 1, '08:00', 'Mon 2026-10-12 08:00'];
        yield 'dimanche depuis un mercredi' => ['2026-10-14 10:00', 7, '23:45', 'Sun 2026-10-18 23:45'];
        yield 'lundi depuis un dimanche' => ['2026-10-18 10:00', 1, '00:00', 'Mon 2026-10-12 00:00'];
        yield 'dimanche depuis un lundi' => ['2026-10-12 10:00', 7, '06:15', 'Sun 2026-10-18 06:15'];
        yield 'heure seule : aujourd\'hui' => ['2026-10-14 10:00', null, '21:30', 'Wed 2026-10-14 21:30'];
        yield 'jour seul : heure actuelle' => ['2026-10-14 10:07', 5, null, 'Fri 2026-10-16 10:07'];
    }
}

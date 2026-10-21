<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Schedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(Schedule::class)]
final class ScheduleTest extends KernelTestCase
{
    #[DataProvider('moments')]
    public function testIsActiveAt(string $moment, bool $expected): void
    {
        self::assertSame($expected, self::weekdaysMorning()->isActiveAt(new \DateTimeImmutable($moment)));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function moments(): iterable
    {
        yield 'lundi pendant la plage' => ['2026-10-12 07:30', true];
        yield 'début inclus' => ['2026-10-12 06:00', true];
        yield 'dernière minute' => ['2026-10-12 09:59', true];
        yield 'fin exclue' => ['2026-10-12 10:00', false];
        yield 'avant la plage' => ['2026-10-12 05:59', false];
        yield 'samedi' => ['2026-10-17 07:30', false];
    }

    public function testWithoutTimeRangeTheWholeDayIsCovered(): void
    {
        $schedule = new Schedule()->setWeekdays([1]);

        self::assertTrue($schedule->isActiveAt(new \DateTimeImmutable('2026-10-12 00:00')));
        self::assertTrue($schedule->isActiveAt(new \DateTimeImmutable('2026-10-12 23:59')));
        self::assertFalse($schedule->isActiveAt(new \DateTimeImmutable('2026-10-13 12:00')));
    }

    public function testEveryDayByDefault(): void
    {
        self::assertSame([1, 2, 3, 4, 5, 6, 7], new Schedule()->getWeekdays());
    }

    #[DataProvider('intersections')]
    public function testIntersects(Schedule $other, bool $expected): void
    {
        self::assertSame($expected, self::weekdaysMorning()->intersects($other));
        self::assertSame($expected, $other->intersects(self::weekdaysMorning()), 'La relation est symétrique.');
    }

    /**
     * @return iterable<string, array{Schedule, bool}>
     */
    public static function intersections(): iterable
    {
        yield 'toute la journée' => [new Schedule(), true];
        yield 'plage qui chevauche' => [self::schedule([1], '09:45', '12:00'), true];
        yield 'plage qui suit' => [self::schedule([1, 2, 3, 4, 5], '10:00', '14:00'), false];
        yield 'plage qui précède' => [self::schedule([1, 2, 3, 4, 5], '00:00', '06:00'), false];
        yield 'plage incluse' => [self::schedule([3], '07:00', '08:00'), true];
        yield 'week-end seulement' => [new Schedule()->setWeekdays([6, 7]), false];
    }

    #[DataProvider('invalidSchedules')]
    public function testValidation(Schedule $schedule, string $path, string $message): void
    {
        $violations = self::getContainer()->get(ValidatorInterface::class)->validate($schedule);

        self::assertCount(1, $violations);
        self::assertSame($path, $violations->get(0)->getPropertyPath());
        self::assertSame($message, $violations->get(0)->getMessage());
    }

    /**
     * @return iterable<string, array{Schedule, string, string}>
     */
    public static function invalidSchedules(): iterable
    {
        yield 'aucun jour' => [new Schedule()->setWeekdays([]), 'weekdays', 'Choisis au moins un jour.'];
        yield 'début sans fin' => [new Schedule()->setStartTime(new \DateTimeImmutable('06:00')), 'endTime', 'Renseigne l\'heure de début et l\'heure de fin, ou aucune des deux.'];
        yield 'fin sans début' => [new Schedule()->setEndTime(new \DateTimeImmutable('06:00')), 'startTime', 'Renseigne l\'heure de début et l\'heure de fin, ou aucune des deux.'];
        yield 'fin avant début' => [self::schedule([1], '10:00', '06:00'), 'endTime', 'L\'heure de fin doit être après l\'heure de début.'];
        yield 'fin égale au début' => [self::schedule([1], '10:00', '10:00'), 'endTime', 'L\'heure de fin doit être après l\'heure de début.'];
    }

    public function testValidSchedules(): void
    {
        $validator = self::getContainer()->get(ValidatorInterface::class);

        self::assertCount(0, $validator->validate(new Schedule()));
        self::assertCount(0, $validator->validate(self::weekdaysMorning()));
    }

    public function testDescription(): void
    {
        self::assertSame('Tous les jours', (string) new Schedule());
        self::assertSame('Lun, Mar, Mer, Jeu, Ven, 06:00–10:00', (string) self::weekdaysMorning());
        self::assertSame('Sam, Dim', (string) new Schedule()->setWeekdays([6, 7]));
    }

    private static function weekdaysMorning(): Schedule
    {
        return self::schedule([1, 2, 3, 4, 5], '06:00', '10:00');
    }

    /**
     * @param list<int> $weekdays
     */
    private static function schedule(array $weekdays, string $start, string $end): Schedule
    {
        return new Schedule()
            ->setWeekdays($weekdays)
            ->setStartTime(new \DateTimeImmutable($start))
            ->setEndTime(new \DateTimeImmutable($end));
    }
}

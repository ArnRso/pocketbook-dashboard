<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Reminder;
use App\Entity\ReminderWindow;
use App\Entity\Weekday;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(Reminder::class)]
#[CoversClass(ReminderWindow::class)]
final class ReminderTest extends KernelTestCase
{
    #[DataProvider('moments')]
    public function testWindowContains(ReminderWindow $window, string $moment, bool $expected): void
    {
        self::assertSame($expected, $window->contains(self::at($moment)));
    }

    /**
     * @return iterable<string, array{ReminderWindow, string, bool}>
     */
    public static function moments(): iterable
    {
        // Du dimanche 16:00 au lundi 09:00 : le 18 octobre 2026 est un dimanche
        $bin = self::window(Weekday::Sunday, '16:00', Weekday::Monday, '09:00');
        yield 'dimanche avant le début' => [$bin, '2026-10-18 15:59', false];
        yield 'début inclus' => [$bin, '2026-10-18 16:00', true];
        yield 'dimanche soir' => [$bin, '2026-10-18 23:59', true];
        yield 'lundi matin' => [$bin, '2026-10-19 08:59', true];
        yield 'fin exclue' => [$bin, '2026-10-19 09:00', false];
        yield 'autre jour' => [$bin, '2026-10-14 20:00', false];

        $weekday = self::window(Weekday::Tuesday, '08:00', Weekday::Thursday, '12:00');
        yield 'sur plusieurs jours' => [$weekday, '2026-10-14 03:00', true];
        yield 'après plusieurs jours' => [$weekday, '2026-10-15 12:00', false];

        $sameDay = self::window(Weekday::Monday, '07:00', Weekday::Monday, '07:30');
        yield 'dans la journée' => [$sameDay, '2026-10-12 07:15', true];
        yield 'le lendemain' => [$sameDay, '2026-10-13 07:15', false];

        // Commence lundi à 10:00 et finit le lundi suivant à 09:00
        $almostWeek = self::window(Weekday::Monday, '10:00', Weekday::Monday, '09:00');
        yield 'presque toute la semaine' => [$almostWeek, '2026-10-15 12:00', true];
        yield 'dans le trou' => [$almostWeek, '2026-10-12 09:30', false];
    }

    #[DataProvider('ends')]
    public function testEndingAfter(ReminderWindow $window, string $moment, string $expected): void
    {
        self::assertSame($expected, $window->endingAfter(self::at($moment))->format('Y-m-d H:i'));
    }

    /**
     * @return iterable<string, array{ReminderWindow, string, string}>
     */
    public static function ends(): iterable
    {
        $bin = self::window(Weekday::Sunday, '16:00', Weekday::Monday, '09:00');
        yield 'le lendemain' => [$bin, '2026-10-18 16:00', '2026-10-19 09:00'];
        yield 'le jour même' => [$bin, '2026-10-19 08:59', '2026-10-19 09:00'];
        yield 'secondes ignorées' => [$bin, '2026-10-18 20:30:45', '2026-10-19 09:00'];
        yield 'la semaine suivante' => [self::window(Weekday::Monday, '10:00', Weekday::Monday, '09:00'), '2026-10-12 10:00', '2026-10-19 09:00'];
        // Passage à l'heure d'hiver dans la nuit du samedi 24 au dimanche 25 octobre 2026
        yield 'heure locale au changement d\'heure' => [self::window(Weekday::Saturday, '20:00', Weekday::Sunday, '09:00'), '2026-10-24 22:00', '2026-10-25 09:00'];
    }

    public function testWindowSpanningTheWholeWeekIsRejected(): void
    {
        $violations = self::getContainer()->get(ValidatorInterface::class)->validate(self::window(Weekday::Friday, '10:00', Weekday::Friday, '10:00'));

        self::assertCount(1, $violations);
        self::assertSame('endTime', $violations->get(0)->getPropertyPath());
        self::assertSame('La fin de la plage doit être différente de son début.', $violations->get(0)->getMessage());
    }

    public function testIncompleteWindowIsRejectedAndNeverActive(): void
    {
        $window = new ReminderWindow()->setStartDay(Weekday::Monday)->setStartTime(new \DateTimeImmutable('08:00'));

        $violations = self::getContainer()->get(ValidatorInterface::class)->validate($window);

        self::assertSame(['endDay', 'endTime'], array_map(static fn ($violation): string => $violation->getPropertyPath(), iterator_to_array($violations)));
        self::assertFalse($window->contains(self::at('2026-10-12 09:00')));
        self::assertSame('Lun 08:00 → ? ?', (string) $window);
    }

    public function testReminderNeedsATextAndAWindow(): void
    {
        $violations = self::getContainer()->get(ValidatorInterface::class)->validate(new Reminder());

        self::assertSame(['text', 'windows'], array_map(static fn ($violation): string => $violation->getPropertyPath(), iterator_to_array($violations)));
        self::assertSame('Ajoute au moins une plage.', $violations->get(1)->getMessage());
    }

    public function testInvalidWindowMakesTheReminderInvalid(): void
    {
        $reminder = new Reminder()->setText('Poubelle')->addWindow(self::window(Weekday::Friday, '10:00', Weekday::Friday, '10:00'));

        $violations = self::getContainer()->get(ValidatorInterface::class)->validate($reminder);

        self::assertCount(1, $violations);
        self::assertSame('windows[0].endTime', $violations->get(0)->getPropertyPath());
    }

    public function testWindowAtReturnsTheCurrentWindow(): void
    {
        $tuesday = self::window(Weekday::Tuesday, '18:00', Weekday::Tuesday, '20:00');
        $sunday = self::window(Weekday::Sunday, '16:00', Weekday::Monday, '09:00');
        $reminder = new Reminder()->setText('Sortir la poubelle verte')->addWindow($tuesday)->addWindow($sunday);

        self::assertSame($sunday, $reminder->windowAt(self::at('2026-10-19 07:00')));
        self::assertSame($tuesday, $reminder->windowAt(self::at('2026-10-13 19:00')));
        self::assertNull($reminder->windowAt(self::at('2026-10-13 21:00')));
        self::assertSame($reminder, $sunday->getReminder());
        self::assertSame('Sortir la poubelle verte', (string) $reminder);
        self::assertSame('Dim 16:00 → Lun 09:00', (string) $sunday);
    }

    public function testDisabledReminderIsNeverActive(): void
    {
        $reminder = new Reminder()->setText('Poubelle')->addWindow(self::window(Weekday::Sunday, '16:00', Weekday::Monday, '09:00'))->setEnabled(false);

        self::assertNull($reminder->windowAt(self::at('2026-10-19 07:00')));
    }

    public function testWindowsCanBeRemovedOnce(): void
    {
        $window = self::window(Weekday::Sunday, '16:00', Weekday::Monday, '09:00');
        $reminder = new Reminder()->addWindow($window)->addWindow($window);

        self::assertCount(1, $reminder->getWindows());

        $reminder->removeWindow($window);

        self::assertCount(0, $reminder->getWindows());
    }

    private static function window(Weekday $startDay, string $startTime, Weekday $endDay, string $endTime): ReminderWindow
    {
        return new ReminderWindow()
            ->setStartDay($startDay)
            ->setStartTime(new \DateTimeImmutable($startTime))
            ->setEndDay($endDay)
            ->setEndTime(new \DateTimeImmutable($endTime));
    }

    private static function at(string $moment): \DateTimeImmutable
    {
        return new \DateTimeImmutable($moment, new \DateTimeZone('Europe/Paris'));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Widget;

use App\Entity\Reminder;
use App\Entity\ReminderWindow;
use App\Entity\Tile\ClockTile;
use App\Entity\Tile\ReminderTile;
use App\Entity\Weekday;
use App\Repository\ReminderRepository;
use App\Widget\ActiveReminder;
use App\Widget\NothingToShowException;
use App\Widget\ReminderView;
use App\Widget\ReminderWidget;
use App\Widget\UnsupportedTileException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(ReminderWidget::class)]
#[CoversClass(ReminderView::class)]
#[CoversClass(ActiveReminder::class)]
#[CoversClass(ReminderRepository::class)]
final class ReminderWidgetTest extends KernelTestCase
{
    public function testActiveRemindersWithTheEndOfTheirWindow(): void
    {
        $this->persist(
            self::reminder('Sortir la poubelle verte', Weekday::Sunday, '16:00', Weekday::Monday, '09:00'),
            self::reminder('Arroser les plantes', Weekday::Monday, '07:00', Weekday::Monday, '08:00'),
            self::reminder('Piscine', Weekday::Wednesday, '17:00', Weekday::Wednesday, '18:00'),
            self::reminder('Désactivé', Weekday::Monday, '00:00', Weekday::Sunday, '23:45')->setEnabled(false),
        );

        $view = $this->widget()->data(new ReminderTile(), self::at('2026-10-19 07:30'));

        self::assertSame(['Sortir la poubelle verte', 'Arroser les plantes'], array_map(static fn (ActiveReminder $reminder): string => $reminder->text, $view->reminders));
        self::assertSame(['2026-10-19 09:00', '2026-10-19 08:00'], array_map(static fn (ActiveReminder $reminder): string => $reminder->until->format('Y-m-d H:i'), $view->reminders));
        self::assertSame('widget/reminders.html.twig', $this->widget()->template());
    }

    public function testNothingToShowWithoutActiveReminder(): void
    {
        $this->persist(self::reminder('Piscine', Weekday::Wednesday, '17:00', Weekday::Wednesday, '18:00'));

        $this->expectException(NothingToShowException::class);

        $this->widget()->data(new ReminderTile(), self::at('2026-10-19 07:30'));
    }

    public function testOtherTilesAreRejected(): void
    {
        $this->expectException(UnsupportedTileException::class);

        $this->widget()->data(new ClockTile(), self::at('2026-10-19 07:30'));
    }

    private function widget(): ReminderWidget
    {
        return new ReminderWidget(self::getContainer()->get(ReminderRepository::class));
    }

    private function persist(Reminder ...$reminders): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach ($reminders as $reminder) {
            $entityManager->persist($reminder);
        }
        $entityManager->flush();
    }

    private static function reminder(string $text, Weekday $startDay, string $startTime, Weekday $endDay, string $endTime): Reminder
    {
        return (new Reminder())->setText($text)->addWindow((new ReminderWindow())
            ->setStartDay($startDay)
            ->setStartTime(new \DateTimeImmutable($startTime))
            ->setEndDay($endDay)
            ->setEndTime(new \DateTimeImmutable($endTime)));
    }

    private static function at(string $moment): \DateTimeImmutable
    {
        return new \DateTimeImmutable($moment, new \DateTimeZone('Europe/Paris'));
    }
}

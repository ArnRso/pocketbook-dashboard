<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Dashboard;
use App\Entity\DashboardLayout;
use App\Entity\Tile\ClockTile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

#[CoversClass(Dashboard::class)]
#[CoversClass(DashboardLayout::class)]
final class DashboardTest extends TestCase
{
    public function testTilesBelongToTheirDashboard(): void
    {
        $tile = new ClockTile();
        $dashboard = new Dashboard()->setName('Entrée')->addTile($tile)->addTile($tile);

        self::assertSame('Entrée', (string) $dashboard);
        self::assertCount(1, $dashboard->getTiles());
        self::assertSame($dashboard, $tile->getDashboard());

        $dashboard->removeTile($tile);

        self::assertCount(0, $dashboard->getTiles());
    }

    public function testOnlyTilesVisibleAtTheMomentAreListed(): void
    {
        $always = new ClockTile();
        $morning = new ClockTile();
        $morning->getSchedule()->setStartTime(new \DateTimeImmutable('06:00'))->setEndTime(new \DateTimeImmutable('10:00'));
        $dashboard = new Dashboard()->addTile($always)->addTile($morning);

        self::assertSame([$always, $morning], $dashboard->tilesVisibleAt(new \DateTimeImmutable('2026-10-12 08:00')));
        self::assertSame([$always], $dashboard->tilesVisibleAt(new \DateTimeImmutable('2026-10-12 12:00')));
        self::assertSame([$morning], $dashboard->tilesHiddenAt(new \DateTimeImmutable('2026-10-12 12:00')));
    }

    public function testAutomaticLayoutByDefault(): void
    {
        self::assertSame(DashboardLayout::Auto, new Dashboard()->getLayout());
        self::assertSame(DashboardLayout::Grid, new Dashboard()->setLayout(DashboardLayout::Grid)->getLayout());
        self::assertSame(['Automatique', 'Grille libre'], array_map(static fn (DashboardLayout $layout): string => $layout->label(), DashboardLayout::cases()));
        self::assertSame('Grille libre', DashboardLayout::Grid->trans(new IdentityTranslator()));
    }

    public function testTilesAreAddedLastAndListedInTheirOrder(): void
    {
        [$first, $second, $third] = [new ClockTile(), new ClockTile(), new ClockTile()];
        $dashboard = new Dashboard()->addTile($first)->addTile($second);
        $first->setPosition(5);
        $dashboard->addTile($third);

        self::assertSame([2, 5, 6], [$second->getPosition(), $first->getPosition(), $third->getPosition()]);
        self::assertSame([$second, $first, $third], $dashboard->orderedTiles());
        self::assertSame([$second, $first, $third], $dashboard->tilesVisibleAt(new \DateTimeImmutable('2026-10-12 08:00')));
    }

    public function testTileIsShiftedWithItsNeighbour(): void
    {
        [$first, $second, $third] = [new ClockTile(), new ClockTile(), new ClockTile()];
        $dashboard = new Dashboard()->addTile($first)->addTile($second)->addTile($third);

        $dashboard->shiftTile($third, -1);
        self::assertSame([$first, $third, $second], $dashboard->orderedTiles());

        $dashboard->shiftTile($first, 1);
        self::assertSame([$third, $first, $second], $dashboard->orderedTiles());
        self::assertSame([1, 2, 3], [$third->getPosition(), $first->getPosition(), $second->getPosition()]);
    }

    public function testShiftBeyondTheEndsChangesNothing(): void
    {
        [$first, $second] = [new ClockTile(), new ClockTile()];
        $dashboard = new Dashboard()->addTile($first)->addTile($second);

        $dashboard->shiftTile($first, -1);
        $dashboard->shiftTile($second, 1);
        $dashboard->shiftTile(new ClockTile(), 1);

        self::assertSame([$first, $second], $dashboard->orderedTiles());
        self::assertSame([1, 2], [$first->getPosition(), $second->getPosition()]);
    }
}

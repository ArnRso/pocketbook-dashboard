<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Dashboard;
use App\Entity\Tile\ClockTile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dashboard::class)]
final class DashboardTest extends TestCase
{
    public function testTilesBelongToTheirDashboard(): void
    {
        $tile = new ClockTile();
        $dashboard = (new Dashboard())->setName('Entrée')->addTile($tile)->addTile($tile);

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
        $dashboard = (new Dashboard())->addTile($always)->addTile($morning);

        self::assertSame([$always, $morning], $dashboard->tilesVisibleAt(new \DateTimeImmutable('2026-10-12 08:00')));
        self::assertSame([$always], $dashboard->tilesVisibleAt(new \DateTimeImmutable('2026-10-12 12:00')));
    }
}

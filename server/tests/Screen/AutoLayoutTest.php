<?php

declare(strict_types=1);

namespace App\Tests\Screen;

use App\Entity\Tile\ClockTile;
use App\Entity\Tile\HourlyForecastTile;
use App\Entity\Tile\Tile;
use App\Entity\Tile\TileSize;
use App\Entity\Tile\TileWidth;
use App\Screen\AutoLayout;
use App\Screen\LayoutRow;
use App\Screen\RenderedTile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Les prévisions ont besoin au plus de 4 lignes sur 16 (1/4 de l'écran), la date de 2 (1/8).
 */
#[CoversClass(AutoLayout::class)]
#[CoversClass(LayoutRow::class)]
#[CoversClass(RenderedTile::class)]
final class AutoLayoutTest extends TestCase
{
    public function testAdaptiveTilesShareTheScreenUpToTheirMaximum(): void
    {
        $rows = (new AutoLayout())->rows([self::hourly('a'), self::hourly('b'), self::hourly('c'), self::hourly('d')]);

        self::assertSame([['a'], ['b'], ['c'], ['d']], self::names($rows));
        self::assertSame([0.25, 0.25, 0.25, 0.25], self::shares($rows));
    }

    public function testSpaceATileDoesNotNeedGoesToTheOthers(): void
    {
        $rows = (new AutoLayout())->rows([self::clock('a'), self::hourly('b'), self::hourly('c'), self::hourly('d'), self::hourly('e')]);

        // La date se contente de 1/8, les prévisions se partagent les 7/8 restants
        self::assertSame([0.125, 0.21875, 0.21875, 0.21875, 0.21875], self::shares($rows));
    }

    public function testBottomOfTheScreenStaysFreeWhenEveryTileHasWhatItNeeds(): void
    {
        $rows = (new AutoLayout())->rows([self::clock('a'), self::hourly('b')]);

        self::assertSame([0.125, 0.25], self::shares($rows));
    }

    public function testImposedHeightsAreKeptAndAdaptiveTilesFillTheRest(): void
    {
        $rows = (new AutoLayout())->rows([
            self::hourly('a', size: TileSize::Large),
            self::hourly('b'),
            self::hourly('c'),
            self::hourly('d'),
        ]);

        self::assertSame([0.375, 0.208333, 0.208333, 0.208333], self::shares($rows));
    }

    public function testImposedHeightIsKeptBeyondTheWidgetMaximum(): void
    {
        $rows = (new AutoLayout())->rows([self::clock('a', size: TileSize::Large), self::clock('b', size: TileSize::Small)]);

        self::assertSame([0.375, 0.125], self::shares($rows));
    }

    public function testEverythingShrinksWhenImposedHeightsExceedTheScreen(): void
    {
        $rows = (new AutoLayout())->rows([
            self::hourly('a', size: TileSize::Large),
            self::hourly('b', size: TileSize::Large),
            self::hourly('c', size: TileSize::Medium),
            self::hourly('d'),
            self::hourly('e'),
        ]);

        // 3/8 + 3/8 + 2/8, plus 1/8 au moins pour chaque tuile adaptative : 10/8 ramenés à l'écran
        self::assertSame([0.3, 0.3, 0.2, 0.1, 0.1], self::shares($rows));
    }

    public function testHalfWidthTilesShareARow(): void
    {
        $rows = (new AutoLayout())->rows([
            self::hourly('a', TileWidth::Half),
            self::hourly('b', TileWidth::Half),
            self::hourly('c'),
        ]);

        self::assertSame([['a', 'b'], ['c']], self::names($rows));
    }

    public function testAdaptiveTileFillsTheGapNextToAHalfWidthTile(): void
    {
        $rows = (new AutoLayout())->rows([
            self::clock('a', TileWidth::Half),
            self::hourly('b'),
            self::hourly('c'),
        ]);

        // La ligne partagée prend la hauteur dont les prévisions ont besoin
        self::assertSame([['a', 'b'], ['c']], self::names($rows));
        self::assertSame([0.25, 0.25], self::shares($rows));
    }

    public function testLoneHalfWidthTileTakesTheWholeRow(): void
    {
        $rows = (new AutoLayout())->rows([
            self::hourly('a', TileWidth::Half),
            self::hourly('b', TileWidth::Full),
            self::hourly('c'),
            self::hourly('d', TileWidth::Half),
        ]);

        self::assertSame([['a'], ['b'], ['c'], ['d']], self::names($rows));
    }

    public function testRowIsAsHighAsItsHighestImposedTile(): void
    {
        $rows = (new AutoLayout())->rows([
            self::hourly('a', TileWidth::Half, TileSize::Small),
            self::hourly('b', TileWidth::Half, TileSize::Medium),
            self::hourly('c', TileWidth::Half, TileSize::Small),
            self::hourly('d'),
            self::hourly('e'),
        ]);

        // c impose sa hauteur à la ligne qu'elle partage avec d
        self::assertSame([['a', 'b'], ['c', 'd'], ['e']], self::names($rows));
        self::assertSame([0.25, 0.125, 0.25], self::shares($rows));
    }

    public function testNothingToLayOut(): void
    {
        self::assertSame([], (new AutoLayout())->rows([]));
    }

    private static function hourly(string $name, ?TileWidth $width = null, ?TileSize $size = null): RenderedTile
    {
        return self::tile(new HourlyForecastTile(), $name, $width, $size);
    }

    private static function clock(string $name, ?TileWidth $width = null, ?TileSize $size = null): RenderedTile
    {
        return self::tile(new ClockTile(), $name, $width, $size);
    }

    private static function tile(Tile $tile, string $name, ?TileWidth $width, ?TileSize $size): RenderedTile
    {
        return new RenderedTile($tile->setAutoWidth($width)->setAutoSize($size), $name);
    }

    /**
     * @param list<LayoutRow> $rows
     *
     * @return list<list<string>>
     */
    private static function names(array $rows): array
    {
        return array_map(static fn (LayoutRow $row): array => array_map(static fn (RenderedTile $tile): string => $tile->html, $row->tiles), $rows);
    }

    /**
     * @param list<LayoutRow> $rows
     *
     * @return list<float>
     */
    private static function shares(array $rows): array
    {
        return array_map(static fn (LayoutRow $row): float => round($row->share, 6), $rows);
    }
}

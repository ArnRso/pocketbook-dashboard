<?php

declare(strict_types=1);

namespace App\Tests\Screen;

use App\Entity\Tile\ClockTile;
use App\Entity\Tile\TileSize;
use App\Entity\Tile\TileWidth;
use App\Screen\AutoLayout;
use App\Screen\LayoutRow;
use App\Screen\RenderedTile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AutoLayout::class)]
#[CoversClass(LayoutRow::class)]
#[CoversClass(RenderedTile::class)]
final class AutoLayoutTest extends TestCase
{
    public function testFullWidthTilesAreStacked(): void
    {
        $rows = (new AutoLayout())->rows([self::tile('a', TileWidth::Full, TileSize::Small), self::tile('b', TileWidth::Full, TileSize::Large)]);

        self::assertSame([['a'], ['b']], self::names($rows));
        self::assertSame([1, 3], self::weights($rows));
    }

    public function testConsecutiveHalfWidthTilesShareARowAsHighAsTheHighest(): void
    {
        $rows = (new AutoLayout())->rows([
            self::tile('a', TileWidth::Half, TileSize::Small),
            self::tile('b', TileWidth::Half, TileSize::Medium),
            self::tile('c', TileWidth::Half, TileSize::Small),
            self::tile('d', TileWidth::Half, TileSize::Small),
        ]);

        self::assertSame([['a', 'b'], ['c', 'd']], self::names($rows));
        self::assertSame([2, 1], self::weights($rows));
    }

    public function testLoneHalfWidthTileTakesTheWholeRow(): void
    {
        $rows = (new AutoLayout())->rows([
            self::tile('a', TileWidth::Half, TileSize::Medium),
            self::tile('b', TileWidth::Full, TileSize::Small),
            self::tile('c', TileWidth::Half, TileSize::Large),
            self::tile('d', TileWidth::Half, TileSize::Small),
            self::tile('e', TileWidth::Half, TileSize::Small),
        ]);

        self::assertSame([['a'], ['b'], ['c', 'd'], ['e']], self::names($rows));
        self::assertSame([2, 1, 3, 1], self::weights($rows));
    }

    public function testNothingToLayOut(): void
    {
        self::assertSame([], (new AutoLayout())->rows([]));
    }

    private static function tile(string $name, TileWidth $width, TileSize $size): RenderedTile
    {
        return new RenderedTile((new ClockTile())->setAutoWidth($width)->setAutoSize($size), $name);
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
     * @return list<int>
     */
    private static function weights(array $rows): array
    {
        return array_map(static fn (LayoutRow $row): int => $row->weight, $rows);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Dashboard;
use App\Entity\DashboardLayout;
use App\Entity\Place;
use App\Entity\Tile\ChabanTile;
use App\Entity\Tile\ClockTile;
use App\Entity\Tile\CommuteTile;
use App\Entity\Tile\CurrentWeatherTile;
use App\Entity\Tile\HourlyForecastTile;
use App\Entity\Tile\RainTile;
use App\Entity\Tile\Tile;
use App\Entity\Tile\TileSize;
use App\Entity\Tile\TileWidth;
use App\Entity\Tile\WeatherTile;
use App\Entity\Trip;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(Tile::class)]
#[CoversClass(ClockTile::class)]
#[CoversClass(WeatherTile::class)]
#[CoversClass(CurrentWeatherTile::class)]
#[CoversClass(RainTile::class)]
#[CoversClass(HourlyForecastTile::class)]
#[CoversClass(CommuteTile::class)]
#[CoversClass(ChabanTile::class)]
#[CoversClass(TileWidth::class)]
#[CoversClass(TileSize::class)]
final class TileTest extends KernelTestCase
{
    #[DataProvider('tiles')]
    public function testEachTileTypeDescribesItself(Tile $tile, string $widget, string $description, int $width, int $height): void
    {
        self::assertSame($widget, $tile->widget());
        self::assertSame($description, $tile->describe());
        self::assertSame([1, 1, $width, $height], [$tile->getColumn(), $tile->getRow(), $tile->getWidth(), $tile->getHeight()]);
    }

    /**
     * @return iterable<string, array{Tile, string, string, int, int}>
     */
    public static function tiles(): iterable
    {
        $home = new Place()->setLabel('Maison');
        $trip = new Trip()->setOrigin($home)->setDestination(new Place()->setLabel('Travail'));

        yield 'date et heure' => [new ClockTile(), 'clock', 'Date et heure', 12, 2];
        yield 'météo actuelle' => [new CurrentWeatherTile()->setPlace($home), 'current_weather', 'Météo actuelle · Maison', 12, 3];
        yield 'pluie' => [new RainTile()->setPlace($home), 'rain', 'Pluie · Maison', 12, 2];
        yield 'prévisions' => [new HourlyForecastTile()->setPlace($home), 'hourly_forecast', 'Prévisions heure par heure · Maison', 12, 4];
        yield 'trajet' => [new CommuteTile()->setTrip($trip), 'commute', 'Trajet · Maison → Travail', 12, 5];
        yield 'pont' => [new ChabanTile(), 'chaban', 'Pont Chaban-Delmas · 7 jours', 12, 3];
    }

    public function testBridgeDaysAreBounded(): void
    {
        self::assertSame('days', $this->validate(new ChabanTile()->setDays(0))->get(0)->getPropertyPath());
        self::assertSame('days', $this->validate(new ChabanTile()->setDays(31))->get(0)->getPropertyPath());
        self::assertSame('days', $this->validate(new ChabanTile()->setDays(null))->get(0)->getPropertyPath());
        self::assertCount(0, $this->validate(new ChabanTile()->setDays(30)));
    }

    #[DataProvider('positions')]
    public function testConflictsWhenOverlappingAtTheSameTime(int $column, int $row, int $width, int $height, bool $expected): void
    {
        $reference = new ClockTile()->place(3, 3, 4, 2);
        $other = new ClockTile()->place($column, $row, $width, $height);

        self::assertSame($expected, $reference->conflictsWith($other));
        self::assertSame($expected, $other->conflictsWith($reference), 'La relation est symétrique.');
    }

    /**
     * Tuile de référence : colonnes 3 à 6, lignes 3 à 4.
     *
     * @return iterable<string, array{int, int, int, int, bool}>
     */
    public static function positions(): iterable
    {
        yield 'même place' => [3, 3, 4, 2, true];
        yield 'recouvre un coin' => [6, 4, 2, 2, true];
        yield 'incluse' => [4, 3, 1, 1, true];
        yield 'collée à droite' => [7, 3, 2, 2, false];
        yield 'collée à gauche' => [1, 3, 2, 2, false];
        yield 'collée dessous' => [3, 5, 4, 2, false];
        yield 'collée dessus' => [3, 1, 4, 2, false];
    }

    public function testSamePlaceAtDifferentTimesIsAllowed(): void
    {
        $morning = new ClockTile()->place(1, 1, 12, 2);
        $morning->getSchedule()->setStartTime(new \DateTimeImmutable('06:00'))->setEndTime(new \DateTimeImmutable('14:00'));
        $evening = new ClockTile()->place(1, 1, 12, 2);
        $evening->getSchedule()->setStartTime(new \DateTimeImmutable('14:00'))->setEndTime(new \DateTimeImmutable('20:00'));

        self::assertFalse($morning->conflictsWith($evening));
    }

    public function testVisibilityFollowsTheSchedule(): void
    {
        $tile = new ClockTile();
        $tile->getSchedule()->setWeekdays([1]);

        self::assertTrue($tile->isVisibleAt(new \DateTimeImmutable('2026-10-12 08:00')));
        self::assertFalse($tile->isVisibleAt(new \DateTimeImmutable('2026-10-13 08:00')));
    }

    public function testValidLayout(): void
    {
        $dashboard = new Dashboard()
            ->addTile(new ClockTile()->place(1, 1, 12, 2))
            ->addTile(new ClockTile()->place(1, 15, 12, 2));

        foreach ($dashboard->getTiles() as $tile) {
            self::assertCount(0, $this->validate($tile));
        }
    }

    public function testTileMustFitInTheGrid(): void
    {
        $violations = $this->validate(new ClockTile()->place(10, 15, 4, 3));

        self::assertCount(2, $violations);
        self::assertSame('width', $violations->get(0)->getPropertyPath());
        self::assertSame('La tuile dépasse le bord droit de la grille (12 colonnes).', $violations->get(0)->getMessage());
        self::assertSame('height', $violations->get(1)->getPropertyPath());
        self::assertSame('La tuile dépasse le bas de la grille (16 lignes).', $violations->get(1)->getMessage());
    }

    #[DataProvider('outOfRange')]
    public function testPositionAndSizeAreBounded(int $column, int $row, int $width, int $height, string $path): void
    {
        $violations = $this->validate(new ClockTile()->place($column, $row, $width, $height));

        self::assertContains($path, array_map(static fn (int $index): string => $violations->get($index)->getPropertyPath(), range(0, \count($violations) - 1)));
    }

    /**
     * @return iterable<string, array{int, int, int, int, string}>
     */
    public static function outOfRange(): iterable
    {
        yield 'colonne 0' => [0, 1, 1, 1, 'column'];
        yield 'ligne 0' => [1, 0, 1, 1, 'row'];
        yield 'largeur nulle' => [1, 1, 0, 1, 'width'];
        yield 'hauteur nulle' => [1, 1, 1, 0, 'height'];
        yield 'colonne 13' => [13, 1, 1, 1, 'column'];
        yield 'ligne 17' => [1, 17, 1, 1, 'row'];
    }

    public function testOverlappingTilesOfTheSameDashboardAreRejected(): void
    {
        $clock = new ClockTile()->place(1, 1, 12, 2);
        $weather = new CurrentWeatherTile()->setPlace(new Place()->setLabel('Maison'))->place(1, 2, 12, 3);
        new Dashboard()->setLayout(DashboardLayout::Grid)->addTile($clock)->addTile($weather);

        $violations = $this->validate($clock);

        self::assertCount(1, $violations);
        self::assertSame('Cette tuile chevauche « Météo actuelle · Maison » à des horaires communs.', $violations->get(0)->getMessage());
    }

    public function testOverlappingDoesNotMatterInAutomaticLayout(): void
    {
        $clock = new ClockTile()->place(1, 1, 12, 2);
        new Dashboard()->addTile($clock)->addTile(new ClockTile()->place(1, 1, 12, 2));

        self::assertCount(0, $this->validate($clock));
    }

    public function testEachTileTypeKnowsTheHeightItNeedsAtMost(): void
    {
        self::assertSame(
            ['clock' => 2, 'current_weather' => 4, 'rain' => 2, 'hourly_forecast' => 4, 'commute' => 3, 'chaban' => 4, 'reminders' => 2, 'transit' => 2],
            array_map(static fn (string $class): int => new $class()->maxHeight(), Tile::TYPES),
        );
    }

    public function testAutomaticLayoutSizeIsAdaptiveUnlessImposed(): void
    {
        self::assertSame([null, null], [new ClockTile()->getAutoWidth(), new ClockTile()->getAutoSize()]);

        $tile = new ClockTile()->setAutoWidth(TileWidth::Half)->setAutoSize(TileSize::Large);

        self::assertSame([TileWidth::Half, TileSize::Large], [$tile->getAutoWidth(), $tile->getAutoSize()]);
        self::assertSame(['Pleine largeur', 'Demi-largeur'], [TileWidth::Full->label(), TileWidth::Half->label()]);
        self::assertSame(['Petite', 'Moyenne', 'Grande'], array_map(static fn (TileSize $size): string => $size->label(), TileSize::cases()));
        self::assertSame([0.125, 0.25, 0.375], array_map(static fn (TileSize $size): float => $size->fraction(), TileSize::cases()));
    }

    public function testSettingsAreRequired(): void
    {
        self::assertSame('Choisis un lieu.', $this->validate(new RainTile())->get(0)->getMessage());
        self::assertSame('Choisis un trajet.', $this->validate(new CommuteTile())->get(0)->getMessage());
    }

    public function testInvalidScheduleIsReported(): void
    {
        $tile = new ClockTile();
        $tile->getSchedule()->setWeekdays([]);

        self::assertSame('schedule.weekdays', $this->validate($tile)->get(0)->getPropertyPath());
    }

    private function validate(Tile $tile): ConstraintViolationListInterface
    {
        return self::getContainer()->get(ValidatorInterface::class)->validate($tile);
    }
}

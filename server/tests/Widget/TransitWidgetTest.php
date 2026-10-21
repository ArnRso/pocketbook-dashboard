<?php

declare(strict_types=1);

namespace App\Tests\Widget;

use App\Entity\Tile\ClockTile;
use App\Entity\Tile\TransitTile;
use App\Entity\TransitStop;
use App\Tests\Transit\TbmFixtures;
use App\Transit\Passage;
use App\Transit\TbmClient;
use App\Widget\TransitBoard;
use App\Widget\TransitView;
use App\Widget\TransitWidget;
use App\Widget\UnsupportedTileException;
use App\Widget\WidgetNotConfiguredException;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Serializer\SerializerInterface;

#[CoversClass(TransitWidget::class)]
#[CoversClass(TransitView::class)]
#[CoversClass(TransitBoard::class)]
#[CoversClass(TransitTile::class)]
final class TransitWidgetTest extends KernelTestCase
{
    private const string NOW = '2026-10-12 13:20:00';

    public function testNextPassagesOfEachStopInItsLineAndDirection(): void
    {
        $tile = new TransitTile()
            ->addStop(self::stop('bordeaux:Line:163:LOC#0', 'E', 'Gare de Blanquefort'))
            ->addStop(self::stop('bordeaux:Line:59:LOC#1', 'A', 'Cenon Gare'));

        $view = $this->widget()->data($tile, new \DateTimeImmutable('2026-10-14 08:00'));

        // Le passage de 13:18 est déjà parti ; le moment affiché par un aperçu n'y change rien
        self::assertSame([['13:24', '13:32', '13:42'], ['13:30']], self::times($view));
        self::assertSame([[true, false, true], [true]], array_map(
            static fn (TransitBoard $board): array => array_map(static fn (Passage $passage): bool => $passage->realtime, $board->passages),
            $view->boards,
        ));
        self::assertSame('Palmer · E → Gare de Blanquefort', (string) ($view->boards[0] ?? self::fail('Aucun arrêt.'))->stop);
        self::assertSame('widget/transit.html.twig', $this->widget()->template());
    }

    public function testNumberOfPassagesIsConfigurable(): void
    {
        $tile = new TransitTile()->setDepartures(5)->addStop(self::stop('bordeaux:Line:163:LOC#0', 'E', 'Gare de Blanquefort'));

        self::assertSame([['13:24', '13:32', '13:42', '13:52']], self::times($this->widget()->data($tile, new \DateTimeImmutable(self::NOW))));
    }

    public function testPassagesAreFetchedOncePerStopAndMinute(): void
    {
        $http = TbmFixtures::http();
        $widget = $this->widget($http);
        $tile = new TransitTile()
            ->addStop(self::stop('bordeaux:Line:163:LOC#0', 'E', 'Gare de Blanquefort'))
            ->addStop(self::stop('bordeaux:Line:163:LOC#1', 'E', 'Floirac Dravemont'));

        $widget->data($tile, new \DateTimeImmutable(self::NOW));
        $widget->data($tile, new \DateTimeImmutable(self::NOW));

        self::assertSame(1, $http->getRequestsCount());
    }

    public function testTileWithoutStopIsReported(): void
    {
        $this->expectException(WidgetNotConfiguredException::class);
        $this->expectExceptionMessageIs('Aucun arrêt choisi');

        $this->widget()->data(new TransitTile(), new \DateTimeImmutable(self::NOW));
    }

    public function testMissingNumberOfPassagesIsReported(): void
    {
        $this->expectException(WidgetNotConfiguredException::class);
        $this->expectExceptionMessageIs('Nombre de passages non renseigné');

        $this->widget()->data(new TransitTile()->setDepartures(null), new \DateTimeImmutable(self::NOW));
    }

    public function testOtherTilesAreRejected(): void
    {
        $this->expectException(UnsupportedTileException::class);

        $this->widget()->data(new ClockTile(), new \DateTimeImmutable(self::NOW));
    }

    public function testTileDescribesItsStops(): void
    {
        $tile = new TransitTile()
            ->addStop(self::stop('bordeaux:Line:163:LOC#0', 'E', 'Gare de Blanquefort'))
            ->addStop(self::stop('bordeaux:Line:59:LOC#1', 'A', 'Cenon Gare'));

        self::assertSame('Prochains passages TBM · Palmer', $tile->describe());
        self::assertSame('Prochains passages TBM', new TransitTile()->describe());
        self::assertSame(3, $tile->maxHeight());

        $stop = $tile->getStops()->first();
        self::assertInstanceOf(TransitStop::class, $stop);
        $tile->addStop($stop)->removeStop($stop);

        self::assertCount(1, $tile->getStops());
    }

    private function widget(?MockHttpClient $http = null): TransitWidget
    {
        $clock = new MockClock(self::NOW, 'Europe/Paris');

        return new TransitWidget(
            new TbmClient($http ?? TbmFixtures::http(), self::getContainer()->get(SerializerInterface::class), $clock),
            new ArrayAdapter(),
            $clock,
        );
    }

    private static function stop(string $route, string $code, string $destination): TransitStop
    {
        return new TransitStop()
            ->setStopArea('bordeaux:StopPoint:BP:TPALMER:LOC')
            ->setRoute($route)
            ->describe('Palmer', $code, $destination);
    }

    /**
     * @return list<list<string>>
     */
    private static function times(TransitView $view): array
    {
        return array_map(
            static fn (TransitBoard $board): array => array_map(static fn (Passage $passage): string => $passage->time->setTimezone(new \DateTimeZone('Europe/Paris'))->format('H:i'), $board->passages),
            $view->boards,
        );
    }
}

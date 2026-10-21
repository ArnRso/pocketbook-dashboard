<?php

declare(strict_types=1);

namespace App\Tests\Transit;

use App\Entity\TransitStop;
use App\Transit\TbmClient;
use App\Transit\TransitException;
use App\Transit\TransitNetwork;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Serializer\SerializerInterface;

#[CoversClass(TransitNetwork::class)]
#[CoversClass(TransitStop::class)]
final class TransitNetworkTest extends KernelTestCase
{
    public function testStopsAreListedWithTheLinesServingThem(): void
    {
        self::assertSame([
            'Gare (1)' => 'bordeaux:StopPoint:BP:GARE1:LOC',
            'Gare (1) · bordeaux:StopPoint:BP:GARE2:LOC' => 'bordeaux:StopPoint:BP:GARE2:LOC',
            'Palmer (A, E)' => 'bordeaux:StopPoint:BP:TPALMER:LOC',
        ], $this->network()->stopChoices());
    }

    public function testLinesAreListedInEachDirection(): void
    {
        self::assertSame([
            'Lianes 1 → ARTIGUES Blancherie' => 'bordeaux:Line:01:LOC#1',
            'Lianes 1 → MERIGNAC Beaudesert' => 'bordeaux:Line:01:LOC#0',
            'Tram A → Cenon Gare / La Gardette / Thiers Benauge' => 'bordeaux:Line:59:LOC#1',
            'Tram A → Hôpital Pellegrin / Le Haillan Rostand' => 'bordeaux:Line:59:LOC#0',
            'Tram E → Floirac Dravemont' => 'bordeaux:Line:163:LOC#1',
            'Tram E → Gare de Blanquefort' => 'bordeaux:Line:163:LOC#0',
        ], $this->network()->routeChoices());
    }

    public function testLinesServingAStop(): void
    {
        self::assertSame([
            'Lianes 1 → ARTIGUES Blancherie' => 'bordeaux:Line:01:LOC#1',
            'Lianes 1 → MERIGNAC Beaudesert' => 'bordeaux:Line:01:LOC#0',
        ], $this->network()->routeChoices('bordeaux:StopPoint:BP:GARE1:LOC'));
        self::assertSame([], $this->network()->routeChoices('bordeaux:StopPoint:BP:INCONNU:LOC'));
    }

    public function testStopIsDescribedWithTheNamesOfTheNetwork(): void
    {
        $stop = new TransitStop()->setStopArea('bordeaux:StopPoint:BP:TPALMER:LOC')->setRoute('bordeaux:Line:163:LOC#0');

        $this->network()->describe($stop);

        self::assertSame('Palmer · E → Gare de Blanquefort', (string) $stop);
        self::assertSame(['Palmer', 'E', 'Gare de Blanquefort'], [$stop->getStopName(), $stop->getLineCode(), $stop->getDestination()]);
    }

    public function testUnknownStopCannotBeDescribed(): void
    {
        $this->expectException(TransitException::class);
        $this->expectExceptionMessageIs('Arrêt ou ligne inconnu du réseau TBM.');

        $this->network()->describe(new TransitStop()->setStopArea('bordeaux:StopPoint:BP:TPALMER:LOC')->setRoute('bordeaux:Line:163:LOC#7'));
    }

    public function testNetworkIsFetchedOnce(): void
    {
        $http = TbmFixtures::http();
        $network = $this->network($http);

        $network->stopChoices();
        $network->routeChoices();
        $network->line('bordeaux:Line:59:LOC');
        $network->stopArea('bordeaux:StopPoint:BP:TPALMER:LOC');

        self::assertSame(2, $http->getRequestsCount());
        self::assertNull($network->line('bordeaux:Line:999:LOC'));
        self::assertNull($network->stopArea('bordeaux:StopPoint:BP:BELCIER:LOC'));
    }

    public function testRouteIsSplitIntoLineAndDirection(): void
    {
        $stop = new TransitStop()->setRoute('bordeaux:Line:163:LOC#1');

        self::assertSame(['bordeaux:Line:163:LOC', '1', 'bordeaux:Line:163:LOC#1'], [$stop->getLine(), $stop->getDirection(), $stop->getRoute()]);

        $stop->setRoute(null)->setStopArea(null);

        self::assertSame(['', '', null, ''], [$stop->getLine(), $stop->getDirection(), $stop->getRoute(), $stop->getStopArea()]);
        self::assertNull($stop->getId());
    }

    private function network(?MockHttpClient $http = null): TransitNetwork
    {
        $client = new TbmClient($http ?? TbmFixtures::http(), self::getContainer()->get(SerializerInterface::class), new MockClock('2026-10-12 13:20:00', 'Europe/Paris'));

        return new TransitNetwork($client, new ArrayAdapter());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Transit;

use App\Transit\Api\AnnotatedLine;
use App\Transit\Api\AnnotatedStopPoint;
use App\Transit\Api\LineDestination;
use App\Transit\Api\LinesResponse;
use App\Transit\Api\Reference;
use App\Transit\Api\StopMonitoringResponse;
use App\Transit\Api\StopPointsResponse;
use App\Transit\Api\StopVisit;
use App\Transit\Passage;
use App\Transit\StopArea;
use App\Transit\TbmClient;
use App\Transit\TransitException;
use App\Transit\TransitLine;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\SerializerInterface;

#[CoversClass(TbmClient::class)]
#[CoversClass(TransitLine::class)]
#[CoversClass(StopArea::class)]
#[CoversClass(Passage::class)]
#[CoversClass(LinesResponse::class)]
#[CoversClass(AnnotatedLine::class)]
#[CoversClass(LineDestination::class)]
#[CoversClass(StopPointsResponse::class)]
#[CoversClass(AnnotatedStopPoint::class)]
#[CoversClass(Reference::class)]
#[CoversClass(StopMonitoringResponse::class)]
#[CoversClass(StopVisit::class)]
final class TbmClientTest extends KernelTestCase
{
    public function testLinesWithTheirDestinations(): void
    {
        $lines = $this->client(self::fixture('tbm-lines.json'))->lines();

        self::assertSame(['1', 'A', 'E'], array_map(static fn (TransitLine $line): string => $line->code, $lines));
        $tramE = $lines[2] ?? self::fail('Pas de tram E.');
        self::assertSame(['bordeaux:Line:163:LOC', 'Tram E'], [$tramE->ref, $tramE->name]);
        self::assertSame(['Gare de Blanquefort', 'Floirac Dravemont'], $tramE->destinations);
    }

    public function testStopAreasGatherTheLinesOfTheirStopPoints(): void
    {
        $areas = $this->client(self::fixture('tbm-stop-points.json'))->stopAreas();

        // Les points sans zone d'arrêt ou sans ligne ne sont plus desservis
        self::assertSame(['bordeaux:StopPoint:BP:TPALMER:LOC', 'bordeaux:StopPoint:BP:GARE1:LOC', 'bordeaux:StopPoint:BP:GARE2:LOC'], array_map(static fn (StopArea $area): string => $area->ref, $areas));
        $palmer = $areas[0] ?? self::fail('Pas d\'arrêt Palmer.');
        self::assertSame('Palmer', $palmer->name);
        self::assertSame(['bordeaux:Line:163:LOC', 'bordeaux:Line:59:LOC'], $palmer->lines);
        self::assertTrue($palmer->isServedBy('bordeaux:Line:59:LOC'));
        self::assertFalse($palmer->isServedBy('bordeaux:Line:01:LOC'));
    }

    public function testPassagesInChronologicalOrder(): void
    {
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = urldecode($url);

            return self::fixture('tbm-stop-monitoring.json');
        }, 'https://bdx.mecatran.com');

        $passages = $this->client($http)->passages('bordeaux:StopPoint:BP:TPALMER:LOC');

        self::assertSame(['https://bdx.mecatran.com/utw/ws/siri/2.0/bordeaux/stop-monitoring.json?AccountKey=opendata-bordeaux-metropole-flux-gtfs-rt&MonitoringRef=bordeaux:StopPoint:BP:TPALMER:LOC'], $requests);
        self::assertSame(
            ['13:18', '13:24', '13:27', '13:30', '13:32', '13:42', '13:52'],
            array_map(static fn (Passage $passage): string => $passage->time->setTimezone(new \DateTimeZone('Europe/Paris'))->format('H:i'), $passages),
        );
        $theoretical = $passages[4] ?? self::fail('Passage manquant.');
        self::assertSame(['bordeaux:Line:163:LOC', '0', false], [$theoretical->line, $theoretical->direction, $theoretical->realtime]);
        self::assertTrue($passages[0]->realtime);
    }

    public function testStopWithoutPassage(): void
    {
        $empty = new MockResponse('{"Siri": {"ServiceDelivery": {"StopMonitoringDelivery": [{"Status": true}]}}}');

        self::assertSame([], $this->client(new MockHttpClient($empty, 'https://bdx.mecatran.com'))->passages('bordeaux:StopPoint:BP:TPALMER:LOC'));
    }

    public function testUnreachableNetworkIsReported(): void
    {
        $this->expectException(TransitException::class);
        $this->expectExceptionMessageMatches('/^TBM est injoignable : HTTP 503/');

        $this->client(new MockHttpClient(new MockResponse('', ['http_code' => 503]), 'https://bdx.mecatran.com'))->lines();
    }

    public function testUnexpectedResponseIsReported(): void
    {
        $this->expectException(TransitException::class);
        $this->expectExceptionMessageMatches('/^Réponse inattendue de TBM : /');

        $this->client(new MockHttpClient(new MockResponse('{"Siri": {}}'), 'https://bdx.mecatran.com'))->lines();
    }

    private function client(MockResponse|MockHttpClient $http): TbmClient
    {
        return new TbmClient(
            $http instanceof MockHttpClient ? $http : new MockHttpClient($http, 'https://bdx.mecatran.com'),
            self::getContainer()->get(SerializerInterface::class),
            new MockClock('2026-10-12 13:20:00', 'Europe/Paris'),
        );
    }

    private static function fixture(string $name): MockResponse
    {
        $json = file_get_contents(__DIR__.'/../fixtures/'.$name);
        self::assertIsString($json);

        return new MockResponse($json);
    }
}

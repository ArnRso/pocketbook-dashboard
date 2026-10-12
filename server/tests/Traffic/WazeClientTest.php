<?php

declare(strict_types=1);

namespace App\Tests\Traffic;

use App\Geo\Coordinates;
use App\Geo\GeocodingException;
use App\Traffic\TrafficException;
use App\Traffic\TrafficStatus;
use App\Traffic\Waze\AutocompletePlace;
use App\Traffic\Waze\AutocompleteResponse;
use App\Traffic\Waze\AutocompleteResponseDenormalizer;
use App\Traffic\Waze\RouteResult;
use App\Traffic\Waze\RoutingResponse;
use App\Traffic\WazeClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\SerializerInterface;

#[CoversClass(WazeClient::class)]
#[CoversClass(RoutingResponse::class)]
#[CoversClass(RouteResult::class)]
#[CoversClass(AutocompleteResponse::class)]
#[CoversClass(AutocompletePlace::class)]
#[CoversClass(AutocompleteResponseDenormalizer::class)]
final class WazeClientTest extends KernelTestCase
{
    public function testRoutesAreDeduplicatedAndSortedByDuration(): void
    {
        $response = self::fixture('waze-routes.json');

        $routes = $this->client($response)->routes(self::from(), self::to());

        self::assertCount(2, $routes);

        self::assertSame('Boulevard Périphérique Nord', $routes[0]->name);
        self::assertSame(19.0, $routes[0]->minutes);
        self::assertSame(18.0, $routes[0]->freeFlowMinutes);
        self::assertSame(9.6, $routes[0]->kilometers);
        self::assertSame(TrafficStatus::Fluid, $routes[0]->status());

        self::assertSame('Rue de Bonnel Lyon', $routes[1]->name);
        self::assertSame(25.0, $routes[1]->minutes);
        self::assertSame(8.0, $routes[1]->delay());
        self::assertSame(TrafficStatus::Slow, $routes[1]->status());

        self::assertSame('GET', $response->getRequestMethod());
        self::assertStringStartsWith('https://routing-livemap-row.waze.com/RoutingManager/routingRequest?', $response->getRequestUrl());
        self::assertSame([
            'from' => 'x:4.832000 y:45.757600',
            'to' => 'x:4.890200 y:45.771900',
            'at' => '0',
            'returnJSON' => 'true',
            'returnGeometries' => 'false',
            'returnInstructions' => 'false',
            'timeout' => '60000',
            'nPaths' => '3',
            'options' => 'AVOID_TRAILS:t,AVOID_TOLL_ROADS:f,AVOID_FERRIES:f',
            'subscription' => '*',
        ], self::query($response));
    }

    public function testTollRoadsCanBeAvoided(): void
    {
        $response = self::fixture('waze-routes.json');

        $this->client($response)->routes(self::from(), self::to(), avoidTolls: true);

        self::assertSame('AVOID_TRAILS:t,AVOID_TOLL_ROADS:t,AVOID_FERRIES:f', self::query($response)['options'] ?? null);
    }

    public function testSingleRouteResponseIsSupported(): void
    {
        $json = '{"response":{"routeName":"A7","results":[{"crossTime":600,"crossTimeWithoutRealTime":540,"length":12000}]}}';

        $routes = $this->client(new MockResponse($json))->routes(self::from(), self::to());

        self::assertCount(1, $routes);
        self::assertSame('A7', $routes[0]->name);
        self::assertSame(10.0, $routes[0]->minutes);
        self::assertSame(9.0, $routes[0]->freeFlowMinutes);
        self::assertSame(12.0, $routes[0]->kilometers);
    }

    public function testFirstOfTwoEquallyFastVariantsIsKept(): void
    {
        $json = '{"alternatives":['
            .'{"response":{"routeName":"A7","results":[{"crossTime":600,"crossTimeWithoutRealTime":600,"length":12000}]}},'
            .'{"response":{"routeName":"A7","results":[{"crossTime":600,"crossTimeWithoutRealTime":600,"length":15000}]}}]}';

        $routes = $this->client(new MockResponse($json))->routes(self::from(), self::to());

        self::assertCount(1, $routes);
        self::assertSame(12.0, $routes[0]->kilometers);
    }

    #[DataProvider('failingRoutingResponses')]
    public function testRoutingFailuresRaiseTrafficException(MockResponse $response, string $expectedMessage): void
    {
        $client = $this->client($response);

        $this->expectException(TrafficException::class);
        $this->expectExceptionMessageMatches($expectedMessage);

        $client->routes(self::from(), self::to());
    }

    /**
     * @return iterable<string, array{MockResponse, string}>
     */
    public static function failingRoutingResponses(): iterable
    {
        yield 'bloqué par Waze' => [new MockResponse('<!doctype html>', ['http_code' => 403]), '/^Waze a refusé la requête \(HTTP 403\)\.$/'];
        yield 'injoignable' => [new MockResponse([new \RuntimeException('Connexion refusée')]), '/^Waze est injoignable : Connexion refusée$/'];
        yield 'erreur Waze' => [new MockResponse('{"error":"No route found"}'), '/^Waze a renvoyé une erreur : No route found$/'];
        yield 'aucun itinéraire' => [new MockResponse('{}'), '/^Waze n\'a renvoyé aucun itinéraire\.$/'];
        yield 'segment incomplet' => [new MockResponse('{"response":{"routeName":"A7","results":[{"crossTime":600}]}}'), '/^Réponse inattendue de Waze : .*crossTimeWithoutRealTime/'];
        yield 'pas du JSON' => [new MockResponse('<html>'), '/^Réponse inattendue de Waze : Syntax error$/'];
    }

    public function testGeocodeSkipsAdvertisements(): void
    {
        $response = self::fixture('waze-autocomplete.json');

        $coordinates = $this->client($response)->geocode('place bellecour lyon', new Coordinates(45.75, 4.83));

        self::assertSame(45.75762, $coordinates->latitude);
        self::assertSame(4.8320198, $coordinates->longitude);
        self::assertStringStartsWith('https://gapi.waze.com/autocomplete/q?', $response->getRequestUrl());
        self::assertSame([
            'q' => 'place bellecour lyon',
            'e' => 'ROW',
            'c' => 'wd',
            'exp' => '8',
            'gxy' => '1',
            'lang' => 'fr',
            'sll' => '45.750000,4.830000',
        ], self::query($response));
    }

    public function testMalformedSuggestionsAreSkipped(): void
    {
        $json = '["q",[["Sans lieu",0,[]],"texte",["Lieu",0,[],{"v":"venues.1","x":4.8,"y":45.7}]]]';

        $coordinates = $this->client(new MockResponse($json))->geocode('q');

        self::assertEquals(new Coordinates(45.7, 4.8), $coordinates);
    }

    public function testGeocodeWithoutReferencePoint(): void
    {
        $response = self::fixture('waze-autocomplete.json');

        $this->client($response)->geocode('place bellecour lyon');

        self::assertArrayNotHasKey('sll', self::query($response));
    }

    #[DataProvider('failingAutocompleteResponses')]
    public function testGeocodingFailuresRaiseGeocodingException(MockResponse $response, string $expectedMessage): void
    {
        $client = $this->client($response);

        $this->expectException(GeocodingException::class);
        $this->expectExceptionMessageMatches($expectedMessage);

        $client->geocode('nulle part');
    }

    /**
     * @return iterable<string, array{MockResponse, string}>
     */
    public static function failingAutocompleteResponses(): iterable
    {
        yield 'aucune suggestion' => [new MockResponse('["nulle part",[]]'), '/^Adresse introuvable : "nulle part"\.$/'];
        yield 'que des publicités' => [new MockResponse('["nulle part",[["Pub",0,[],{"v":"advertisement.poi-1","x":4.8,"y":45.7}]]]'), '/^Adresse introuvable/'];
        yield 'sans coordonnées' => [new MockResponse('["nulle part",[["Lieu",0,[],{"v":"venues.1"}]]]'), '/^Adresse introuvable/'];
        yield 'sans longitude' => [new MockResponse('["nulle part",[["Lieu",0,[],{"v":"venues.1","y":45.7}]]]'), '/^Adresse introuvable/'];
        yield 'suggestions illisibles' => [new MockResponse('["nulle part"]'), '/^Réponse inattendue de Waze : La réponse de la recherche Waze devrait contenir une liste de suggestions\.$/'];
        yield 'bloqué par Waze' => [new MockResponse('', ['http_code' => 403]), '/^Waze a refusé la requête \(HTTP 403\)\.$/'];
    }

    private function client(MockResponse $response): WazeClient
    {
        return new WazeClient(new MockHttpClient($response), self::getContainer()->get(SerializerInterface::class));
    }

    private static function from(): Coordinates
    {
        return new Coordinates(45.7576, 4.832);
    }

    private static function to(): Coordinates
    {
        return new Coordinates(45.7719, 4.8902);
    }

    private static function fixture(string $name): MockResponse
    {
        $json = file_get_contents(__DIR__.'/../fixtures/'.$name);
        self::assertIsString($json);

        return new MockResponse($json);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function query(MockResponse $response): array
    {
        parse_str((string) parse_url($response->getRequestUrl(), \PHP_URL_QUERY), $query);

        return $query;
    }
}

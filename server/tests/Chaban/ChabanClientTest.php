<?php

declare(strict_types=1);

namespace App\Tests\Chaban;

use App\Chaban\BridgeClosure;
use App\Chaban\ChabanClient;
use App\Chaban\ChabanException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\SerializerInterface;

#[CoversClass(ChabanClient::class)]
#[CoversClass(BridgeClosure::class)]
final class ChabanClientTest extends KernelTestCase
{
    public function testRequestCoversThePeriodAndThePreviousDay(): void
    {
        $response = new MockResponse(self::fixture());

        $this->client($response)->closures(self::at('2026-10-12 08:00'), self::at('2026-10-19 08:00'));

        self::assertStringStartsWith('https://opendata.bordeaux-metropole.fr/api/explore/v2.1/catalog/datasets/previsions_pont_chaban/records?', $response->getRequestUrl());
        parse_str((string) parse_url($response->getRequestUrl(), \PHP_URL_QUERY), $query);
        self::assertSame([
            'where' => "date_passage >= date'2026-10-11' and date_passage <= date'2026-10-19'",
            'order_by' => 'date_passage, fermeture_a_la_circulation',
            'limit' => '100',
        ], $query);
    }

    public function testClosuresOverlappingThePeriod(): void
    {
        $closures = $this->client(new MockResponse(self::fixture()))->closures(self::at('2026-10-12 08:00'), self::at('2026-10-20 00:00'));

        self::assertSame(
            ['2026-10-17 10:34 → 2026-10-17 11:57 Silver Dawn', '2026-10-19 11:34 → 2026-10-19 12:57 Silver Dawn'],
            array_map(self::describe(...), $closures),
        );
        self::assertTrue(self::first($closures)->total);
        self::assertSame('Europe/Paris', self::first($closures)->start->getTimezone()->getName());
    }

    public function testNightClosureEndsTheNextDay(): void
    {
        $closures = $this->client(new MockResponse(self::fixture()))->closures(self::at('2026-11-15 00:00'), self::at('2026-11-17 00:00'));

        self::assertSame(['2026-11-15 23:00 → 2026-11-16 05:00 Maintenance'], array_map(self::describe(...), $closures));
        $closure = self::first($closures);
        self::assertTrue($closure->isOngoingAt(self::at('2026-11-16 02:00')));
        self::assertFalse($closure->isOngoingAt(self::at('2026-11-16 05:00')));
        self::assertFalse($closure->isOngoingAt(self::at('2026-11-15 22:59')));
    }

    public function testClosureStartedTheDayBeforeIsIncluded(): void
    {
        $closures = $this->client(new MockResponse(self::fixture()))->closures(self::at('2026-11-16 02:00'), self::at('2026-11-17 00:00'));

        self::assertSame(['2026-11-15 23:00 → 2026-11-16 05:00 Maintenance'], array_map(self::describe(...), $closures));
    }

    public function testPartialClosure(): void
    {
        $json = str_replace('"type_de_fermeture": "Totale", "fermeture_totale": "oui"}]', '"type_de_fermeture": "Partielle", "fermeture_totale": "non"}]', self::fixture(), $count);
        self::assertSame(1, $count);

        $closures = $this->client(new MockResponse($json))->closures(self::at('2026-11-15 00:00'), self::at('2026-11-17 00:00'));

        self::assertFalse(self::first($closures)->total);
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadRaisesChabanException(string $search, string $replace, string $expectedMessage): void
    {
        $json = str_replace($search, $replace, self::fixture(), $count);
        self::assertSame(1, $count, 'La fixture doit contenir le fragment à corrompre.');

        $this->expectException(ChabanException::class);
        $this->expectExceptionMessageMatches($expectedMessage);

        $this->client(new MockResponse($json))->closures(self::at('2026-10-12 08:00'), self::at('2026-12-01 00:00'));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'heure illisible' => ['"fermeture_a_la_circulation": "23:00"', '"fermeture_a_la_circulation": "23h"', '/^Réponse inattendue de Bordeaux Métropole : heure « 23h » illisible\.$/'];
        yield 'heure impossible' => ['"re_ouverture_a_la_circulation": "05:00"', '"re_ouverture_a_la_circulation": "25:00"', '/heure « 25:00 » illisible/'];
        yield 'date manquante' => ['"date_passage": "2026-11-15", ', '', '/^Réponse inattendue de Bordeaux Métropole : .*date/'];
        yield 'pas du JSON' => ['{"total_count"', '<html>{"total_count"', '/^Réponse inattendue de Bordeaux Métropole : /'];
    }

    public function testHttpErrorRaisesChabanException(): void
    {
        $client = $this->client(new MockResponse('', ['http_code' => 503]));

        $this->expectException(ChabanException::class);
        $this->expectExceptionMessageMatches('/^Bordeaux Métropole est injoignable : HTTP 503/');

        $client->closures(self::at('2026-10-12 08:00'), self::at('2026-10-19 08:00'));
    }

    private function client(MockResponse $response): ChabanClient
    {
        return new ChabanClient(
            new MockHttpClient($response, 'https://opendata.bordeaux-metropole.fr'),
            self::getContainer()->get(SerializerInterface::class),
        );
    }

    private static function at(string $moment): \DateTimeImmutable
    {
        return new \DateTimeImmutable($moment, new \DateTimeZone('Europe/Paris'));
    }

    /**
     * @param list<BridgeClosure> $closures
     */
    private static function first(array $closures): BridgeClosure
    {
        return $closures[0] ?? self::fail('Aucune fermeture.');
    }

    private static function describe(BridgeClosure $closure): string
    {
        return \sprintf('%s → %s %s', $closure->start->format('Y-m-d H:i'), $closure->end->format('Y-m-d H:i'), $closure->reason);
    }

    private static function fixture(): string
    {
        $json = file_get_contents(__DIR__.'/../fixtures/chaban-closures.json');
        self::assertIsString($json);

        return $json;
    }
}

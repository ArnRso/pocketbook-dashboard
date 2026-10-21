<?php

declare(strict_types=1);

namespace App\Tests\Validator;

use App\Entity\TransitStop;
use App\Tests\Transit\TbmFixtures;
use App\Validator\ServedTransitStop;
use App\Validator\ServedTransitStopValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(ServedTransitStop::class)]
#[CoversClass(ServedTransitStopValidator::class)]
final class ServedTransitStopValidatorTest extends KernelTestCase
{
    private bool $unreachable = false;

    protected function setUp(): void
    {
        self::getContainer()->set('tbm.client', new MockHttpClient(fn (string $method, string $url): MockResponse => $this->unreachable
            ? new MockResponse('', ['http_code' => 503])
            : TbmFixtures::response($url), 'https://bdx.mecatran.com'));
    }

    public function testStopServedByTheLineIsValid(): void
    {
        self::assertSame([], $this->violations('bordeaux:StopPoint:BP:TPALMER:LOC', 'bordeaux:Line:163:LOC#0'));
    }

    /**
     * @param array<string, string> $expected chemin => message
     */
    #[DataProvider('invalidStops')]
    public function testInvalidStopIsRejected(?string $stopArea, ?string $route, array $expected): void
    {
        self::assertSame($expected, $this->violations($stopArea, $route));
    }

    /**
     * @return iterable<string, array{?string, ?string, array<string, string>}>
     */
    public static function invalidStops(): iterable
    {
        yield 'rien de choisi' => [null, null, ['stopArea' => 'Choisis un arrêt.', 'route' => 'Choisis une ligne et sa direction.']];
        yield 'ligne qui ne dessert pas l\'arrêt' => ['bordeaux:StopPoint:BP:TPALMER:LOC', 'bordeaux:Line:01:LOC#0', ['route' => 'La ligne Lianes 1 ne dessert pas cet arrêt.']];
        yield 'arrêt inconnu' => ['bordeaux:StopPoint:BP:INCONNU:LOC', 'bordeaux:Line:163:LOC#0', ['' => 'Arrêt ou ligne inconnu du réseau TBM.']];
        yield 'ligne inconnue' => ['bordeaux:StopPoint:BP:TPALMER:LOC', 'bordeaux:Line:999:LOC#0', ['' => 'Arrêt ou ligne inconnu du réseau TBM.']];
        yield 'direction inconnue' => ['bordeaux:StopPoint:BP:TPALMER:LOC', 'bordeaux:Line:163:LOC#2', ['' => 'Arrêt ou ligne inconnu du réseau TBM.']];
    }

    public function testUnreachableNetworkIsReported(): void
    {
        $this->unreachable = true;

        $violations = $this->violations('bordeaux:StopPoint:BP:TPALMER:LOC', 'bordeaux:Line:163:LOC#0');

        self::assertStringStartsWith('TBM est injoignable : HTTP 503', $violations[''] ?? '');
    }

    /**
     * @return array<string, string> chemin => message
     */
    private function violations(?string $stopArea, ?string $route): array
    {
        $stop = new TransitStop()->setStopArea($stopArea)->setRoute($route);
        $violations = [];
        foreach (self::getContainer()->get(ValidatorInterface::class)->validate($stop) as $violation) {
            $violations[$violation->getPropertyPath()] = (string) $violation->getMessage();
        }

        return $violations;
    }
}

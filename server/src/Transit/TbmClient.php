<?php

declare(strict_types=1);

namespace App\Transit;

use App\Transit\Api\AnnotatedLine;
use App\Transit\Api\LinesResponse;
use App\Transit\Api\Reference;
use App\Transit\Api\StopMonitoringResponse;
use App\Transit\Api\StopPointsResponse;
use App\Transit\Api\StopVisit;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Réseau TBM (bus et tram de Bordeaux Métropole) et ses passages en temps réel, par l'API SIRI-Lite
 * ouverte de Bordeaux Métropole.
 *
 * @see https://transport.data.gouv.fr/resources/83034
 */
class TbmClient
{
    private const BASE_PATH = '/utw/ws/siri/2.0/bordeaux/';

    /** Clé publique des données ouvertes, donnée par la documentation */
    private const ACCOUNT_KEY = 'opendata-bordeaux-metropole-flux-gtfs-rt';

    public function __construct(
        #[Target('tbm.client')]
        private readonly HttpClientInterface $tbmClient,
        private readonly SerializerInterface $serializer,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return list<TransitLine>
     *
     * @throws TransitException
     */
    public function lines(): array
    {
        return array_map(self::line(...), $this->get('lines-discovery', [], LinesResponse::class)->lines);
    }

    /**
     * Zones d'arrêt desservies, avec les lignes de tous leurs quais.
     *
     * @return list<StopArea>
     *
     * @throws TransitException
     */
    public function stopAreas(): array
    {
        $response = $this->get('stoppoints-discovery', [], StopPointsResponse::class);

        $names = [];
        $lines = [];
        foreach ($response->stopPoints as $point) {
            if (null === $point->area || [] === $point->lines) {
                continue;
            }
            $names[$point->area] = $point->name;
            $lines[$point->area] = [...$lines[$point->area] ?? [], ...array_map(static fn (Reference $line): string => $line->value, $point->lines)];
        }

        return array_map(
            static fn (string $area, string $name): StopArea => new StopArea($area, $name, array_values(array_unique($lines[$area] ?? []))),
            array_keys($names),
            array_values($names),
        );
    }

    /**
     * Prochains passages à la zone d'arrêt, toutes lignes et directions confondues, dans l'ordre chronologique.
     *
     * @return list<Passage>
     *
     * @throws TransitException
     */
    public function passages(string $stopArea): array
    {
        $response = $this->get('stop-monitoring', ['MonitoringRef' => $stopArea], StopMonitoringResponse::class);

        $passages = array_map(static fn (StopVisit $visit): Passage => new Passage(
            line: $visit->line,
            direction: $visit->direction,
            time: $visit->expected ?? $visit->aimed,
            realtime: null !== $visit->expected,
        ), $response->visits);
        usort($passages, static fn (Passage $a, Passage $b): int => $a->time <=> $b->time);

        return $passages;
    }

    private static function line(AnnotatedLine $line): TransitLine
    {
        $destinations = [];
        foreach ($line->destinations as $destination) {
            $destinations[$destination->direction] = $destination->name;
        }

        return new TransitLine($line->ref, $line->code, $line->name, $destinations);
    }

    /**
     * @template T of object
     *
     * @param array<string, string> $query
     * @param class-string<T>       $type
     *
     * @return T
     *
     * @throws TransitException
     */
    private function get(string $method, array $query, string $type): object
    {
        try {
            $json = $this->tbmClient->request('GET', self::BASE_PATH.$method.'.json', [
                'query' => ['AccountKey' => self::ACCOUNT_KEY, ...$query],
            ])->getContent();

            $response = $this->serializer->deserialize($json, $type, 'json', [
                DateTimeNormalizer::TIMEZONE_KEY => $this->clock->now()->getTimezone(),
            ]);
        } catch (HttpException $e) {
            throw new TransitException('TBM est injoignable : '.$e->getMessage(), previous: $e);
        } catch (SerializerException $e) {
            throw new TransitException('Réponse inattendue de TBM : '.$e->getMessage(), previous: $e);
        }

        return $response instanceof $type ? $response : throw new TransitException('Réponse inattendue de TBM.');
    }
}

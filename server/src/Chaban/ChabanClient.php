<?php

declare(strict_types=1);

namespace App\Chaban;

use App\Chaban\Api\ClosureRecord;
use App\Chaban\Api\RecordsResponse;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fermetures prévues du pont Jacques Chaban-Delmas, publiées en données ouvertes par Bordeaux Métropole.
 *
 * @see https://opendata.bordeaux-metropole.fr/explore/dataset/previsions_pont_chaban/
 */
class ChabanClient
{
    private const RECORDS_PATH = '/api/explore/v2.1/catalog/datasets/previsions_pont_chaban/records';
    private const MAX_RECORDS = 100;

    public function __construct(
        #[Target('bordeaux_metropole.client')]
        private readonly HttpClientInterface $bordeauxMetropoleClient,
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * Fermetures qui chevauchent la période, dans l'ordre chronologique.
     *
     * @return list<BridgeClosure>
     *
     * @throws ChabanException
     */
    public function closures(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        // La veille aussi : une fermeture de nuit commencée la veille peut être encore en cours
        $where = \sprintf("date_passage >= date'%s' and date_passage <= date'%s'", $from->modify('-1 day')->format('Y-m-d'), $to->format('Y-m-d'));

        try {
            $json = $this->bordeauxMetropoleClient->request('GET', self::RECORDS_PATH, [
                'query' => [
                    'where' => $where,
                    'order_by' => 'date_passage, fermeture_a_la_circulation',
                    'limit' => self::MAX_RECORDS,
                ],
            ])->getContent();

            $response = $this->serializer->deserialize($json, RecordsResponse::class, 'json', [
                DateTimeNormalizer::TIMEZONE_KEY => $from->getTimezone(),
            ]);
        } catch (HttpException $e) {
            throw new ChabanException('Bordeaux Métropole est injoignable : '.$e->getMessage(), previous: $e);
        } catch (SerializerException $e) {
            throw new ChabanException('Réponse inattendue de Bordeaux Métropole : '.$e->getMessage(), previous: $e);
        }

        return array_values(array_filter(
            array_map(self::closure(...), $response->results),
            static fn (BridgeClosure $closure): bool => $closure->overlaps($from, $to),
        ));
    }

    private static function closure(ClosureRecord $record): BridgeClosure
    {
        $start = self::at($record->date, $record->closing);
        $end = self::at($record->date, $record->reopening);
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }

        return new BridgeClosure(
            start: $start,
            end: $end,
            reason: mb_convert_case($record->reason, \MB_CASE_TITLE),
            total: 'Totale' === $record->type,
        );
    }

    private static function at(\DateTimeImmutable $date, string $time): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('!H:i', $time);
        if (false === $parsed || $parsed->format('H:i') !== $time) {
            throw new ChabanException(\sprintf('Réponse inattendue de Bordeaux Métropole : heure « %s » illisible.', $time));
        }

        return $date->setTime((int) $parsed->format('G'), (int) $parsed->format('i'));
    }
}

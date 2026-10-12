<?php

declare(strict_types=1);

namespace App\Chaban\Api;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

/**
 * Fermeture prévue du pont, telle que publiée par Bordeaux Métropole (jeu « previsions_pont_chaban »).
 */
final readonly class ClosureRecord
{
    public function __construct(
        #[SerializedName('date_passage')]
        #[Context([DateTimeNormalizer::FORMAT_KEY => '!Y-m-d'])]
        public \DateTimeImmutable $date,
        /** Heure de fermeture, « HH:MM » */
        #[SerializedName('fermeture_a_la_circulation')]
        public string $closing,
        /** Heure de réouverture, « HH:MM », le lendemain si elle précède la fermeture */
        #[SerializedName('re_ouverture_a_la_circulation')]
        public string $reopening,
        #[SerializedName('bateau')]
        public string $reason,
        #[SerializedName('type_de_fermeture')]
        public string $type,
    ) {
    }
}

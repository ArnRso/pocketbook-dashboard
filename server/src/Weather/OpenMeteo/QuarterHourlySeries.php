<?php

declare(strict_types=1);

namespace App\Weather\OpenMeteo;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

/**
 * Cumuls de pluie par quart d'heure.
 */
final readonly class QuarterHourlySeries
{
    /**
     * @param list<\DateTimeImmutable> $time
     * @param list<float|null>         $precipitation
     */
    public function __construct(
        #[Context([DateTimeNormalizer::FORMAT_KEY => Series::DATETIME_FORMAT])]
        public array $time,
        public array $precipitation,
    ) {
    }
}

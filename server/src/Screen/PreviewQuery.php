<?php

declare(strict_types=1);

namespace App\Screen;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

/**
 * Paramètres de l'aperçu : ?at=2026-10-13T08:00 pour voir l'écran tel qu'il serait à ce moment.
 */
final readonly class PreviewQuery
{
    public const DATE_FORMAT = 'Y-m-d\TH:i';

    public function __construct(
        #[Context([DateTimeNormalizer::FORMAT_KEY => '!'.self::DATE_FORMAT])]
        public ?\DateTimeImmutable $at = null,
    ) {
    }
}

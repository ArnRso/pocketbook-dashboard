<?php

declare(strict_types=1);

namespace App\Transit\Api;

/**
 * Valeur SIRI enveloppée : {"value": "bordeaux:Line:163:LOC"}.
 */
final readonly class Reference
{
    public function __construct(
        public string $value,
    ) {
    }
}

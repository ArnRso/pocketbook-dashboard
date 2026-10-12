<?php

declare(strict_types=1);

namespace App\Transit;

/**
 * Passage d'un véhicule à un arrêt.
 */
final readonly class Passage
{
    /**
     * @param bool $realtime heure estimée en temps réel, sinon heure théorique
     */
    public function __construct(
        public string $line,
        public string $direction,
        public \DateTimeImmutable $time,
        public bool $realtime,
    ) {
    }
}

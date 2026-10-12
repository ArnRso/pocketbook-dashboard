<?php

declare(strict_types=1);

namespace App\Widget;

final readonly class ClockView
{
    /**
     * @param \DateTimeImmutable|null $until   prochain rafraîchissement de la liseuse : l'écran vaut jusque-là
     * @param int|null                $battery niveau de batterie de la liseuse en %, inconnu pour un aperçu sans liseuse
     */
    public function __construct(
        public \DateTimeImmutable $moment,
        public ?\DateTimeImmutable $until = null,
        public ?int $battery = null,
        public bool $charging = false,
    ) {
    }
}

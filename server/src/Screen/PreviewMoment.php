<?php

declare(strict_types=1);

namespace App\Screen;

use Symfony\Component\Clock\ClockInterface;

/**
 * Traduit le jour et l'heure choisis pour un aperçu en un moment de la semaine en cours.
 */
final class PreviewMoment
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return \DateTimeImmutable|null null si rien n'est choisi : l'aperçu montre alors le moment présent
     */
    public function resolve(PreviewQuery $query): ?\DateTimeImmutable
    {
        if ($query->isNow()) {
            return null;
        }

        $now = $this->clock->now();
        $day = $query->day ?? (int) $now->format('N');
        $time = null !== $query->time ? \DateTimeImmutable::createFromFormat('!H:i', $query->time) : $now;
        if (false === $time) {
            throw new \InvalidArgumentException(\sprintf('Heure invalide : %s.', $query->time));
        }

        $monday = $now->modify(\sprintf('-%d days', (int) $now->format('N') - 1));

        return $monday
            ->modify(\sprintf('+%d days', $day - 1))
            ->setTime((int) $time->format('G'), (int) $time->format('i'));
    }
}

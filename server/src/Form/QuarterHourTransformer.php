<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Convertit une heure (DateTimeImmutable) en « HH:MM » au quart d'heure, et inversement.
 *
 * @implements DataTransformerInterface<\DateTimeImmutable, string>
 */
final class QuarterHourTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        // Une heure saisie avant l'arrivée de la liste (ex. 23:59) est présentée au quart d'heure inférieur
        $minutes = intdiv((int) $value->format('i'), 15) * 15;

        return \sprintf('%s:%02d', $value->format('H'), $minutes);
    }

    public function reverseTransform(mixed $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $time = \DateTimeImmutable::createFromFormat('!H:i', $value);
        if (false === $time) {
            throw new TransformationFailedException(\sprintf('Heure invalide : %s.', $value));
        }

        return $time;
    }
}

<?php

declare(strict_types=1);

namespace App\Screen;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Moment simulé dans l'aperçu (?day=2&time=08:00), pour voir quels widgets s'affichent ce jour-là à
 * cette heure. Rempli par la query string ou par le formulaire de l'admin, d'où ses propriétés publiques.
 */
final class PreviewQuery
{
    /** Jour de la semaine (1 = lundi … 7 = dimanche) ; aujourd'hui s'il est omis */
    #[Assert\Range(min: 1, max: 7)]
    public ?int $day = null;

    /** Heure au quart d'heure ; l'heure actuelle si elle est omise */
    #[Assert\Regex('/^([01]\d|2[0-3]):(00|15|30|45)$/', message: 'Heure attendue au quart d\'heure, par exemple 08:15.')]
    public ?string $time = null;

    public function isEmpty(): bool
    {
        return null === $this->day && null === $this->time;
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Jours de la semaine, numérotés comme en ISO-8601 (et comme le format « N » de PHP).
 */
enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Lundi',
            self::Tuesday => 'Mardi',
            self::Wednesday => 'Mercredi',
            self::Thursday => 'Jeudi',
            self::Friday => 'Vendredi',
            self::Saturday => 'Samedi',
            self::Sunday => 'Dimanche',
        };
    }

    /**
     * Choix pour les formulaires : libellé => numéro.
     *
     * @return array<string, int>
     */
    public static function choices(): array
    {
        return array_combine(
            array_map(static fn (self $day): string => $day->label(), self::cases()),
            array_map(static fn (self $day): int => $day->value, self::cases()),
        );
    }
}

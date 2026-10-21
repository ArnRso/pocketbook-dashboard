<?php

declare(strict_types=1);

namespace App\Weather\OpenMeteo;

/**
 * Lecture des séries d'Open-Meteo, dont toutes les colonnes doivent avoir la même longueur.
 */
final class Series
{
    public const string DATETIME_FORMAT = '!Y-m-d\TH:i';
    public const string DATE_FORMAT = '!Y-m-d';

    /**
     * @template T
     *
     * @param list<T> $values
     *
     * @return T
     */
    public static function at(array $values, int $index): mixed
    {
        if (!\array_key_exists($index, $values)) {
            throw new \UnexpectedValueException(\sprintf('Série Open-Meteo incomplète : aucune valeur en position %d.', $index));
        }

        return $values[$index];
    }
}

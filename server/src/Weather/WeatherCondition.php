<?php

declare(strict_types=1);

namespace App\Weather;

/**
 * Traduit un code météo WMO (celui renvoyé par Open-Meteo) en libellé et en icône.
 */
final readonly class WeatherCondition
{
    private const array CODES = [
        0 => ['Ciel dégagé', 'clear'],
        1 => ['Peu nuageux', 'partly'],
        2 => ['Partiellement nuageux', 'partly'],
        3 => ['Couvert', 'cloudy'],
        45 => ['Brouillard', 'fog'],
        48 => ['Brouillard givrant', 'fog'],
        51 => ['Bruine légère', 'drizzle'],
        53 => ['Bruine', 'drizzle'],
        55 => ['Bruine dense', 'drizzle'],
        56 => ['Bruine verglaçante', 'drizzle'],
        57 => ['Bruine verglaçante', 'drizzle'],
        61 => ['Pluie faible', 'rain'],
        63 => ['Pluie modérée', 'rain'],
        65 => ['Pluie forte', 'rain'],
        66 => ['Pluie verglaçante', 'rain'],
        67 => ['Pluie verglaçante', 'rain'],
        71 => ['Neige faible', 'snow'],
        73 => ['Neige modérée', 'snow'],
        75 => ['Neige forte', 'snow'],
        77 => ['Grains de neige', 'snow'],
        80 => ['Averses faibles', 'rain'],
        81 => ['Averses', 'rain'],
        82 => ['Averses violentes', 'rain'],
        85 => ['Averses de neige', 'snow'],
        86 => ['Averses de neige', 'snow'],
        95 => ['Orage', 'storm'],
        96 => ['Orage avec grêle', 'storm'],
        99 => ['Orage avec grêle', 'storm'],
    ];

    public function __construct(
        public string $label,
        public string $icon,
    ) {
    }

    public static function fromWmoCode(int $code, bool $isDay): self
    {
        [$label, $icon] = self::CODES[$code] ?? ['Inconnu', 'cloudy'];

        if (!$isDay && \in_array($icon, ['clear', 'partly'], true)) {
            $icon .= '-night';
        }

        return new self($label, $icon);
    }
}

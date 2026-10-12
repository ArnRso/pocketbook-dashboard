<?php

declare(strict_types=1);

namespace App\Weather;

use App\Entity\Place;
use App\Widget\CacheKey;
use App\Widget\WidgetNotConfiguredException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Prévisions d'un lieu, partagées par les widgets météo et mises en cache : plusieurs tuiles pour le
 * même lieu ne font qu'un appel à Open-Meteo.
 */
class ForecastProvider
{
    public function __construct(
        private readonly OpenMeteoClient $openMeteo,
        #[Target('cache.weather')]
        private readonly CacheInterface $weatherCache,
    ) {
    }

    /**
     * @throws WeatherException
     * @throws WidgetNotConfiguredException
     */
    public function forecast(Place $place): WeatherReport
    {
        $coordinates = $place->getCoordinates()
            ?? throw new WidgetNotConfiguredException(\sprintf('Coordonnées manquantes pour le lieu %s', $place->getLabel()));
        $key = CacheKey::of('weather', $coordinates->latitude, $coordinates->longitude);

        return $this->weatherCache->get($key, fn (): WeatherReport => $this->openMeteo->forecast($coordinates));
    }
}

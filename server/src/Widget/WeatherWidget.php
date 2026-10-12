<?php

declare(strict_types=1);

namespace App\Widget;

use App\Repository\PlaceRepository;
use App\Weather\OpenMeteoClient;
use App\Weather\WeatherReport;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;

#[AsTaggedItem('weather')]
final class WeatherWidget implements WidgetInterface
{
    public function __construct(
        private readonly PlaceRepository $places,
        private readonly OpenMeteoClient $openMeteo,
        #[Target('cache.weather')]
        private readonly CacheInterface $weatherCache,
    ) {
    }

    public function template(): string
    {
        return 'widget/weather.html.twig';
    }

    public function data(): WeatherView
    {
        $place = $this->places->findWeatherLocation() ?? throw new WidgetNotConfiguredException('Aucun lieu météo configuré');
        $coordinates = $place->getCoordinates()
            ?? throw new WidgetNotConfiguredException(\sprintf('Coordonnées manquantes pour le lieu météo %s', $place->getLabel()));

        $key = CacheKey::of('weather', $coordinates->latitude, $coordinates->longitude);

        return new WeatherView(
            place: $place->getLabel(),
            report: $this->weatherCache->get($key, fn (): WeatherReport => $this->openMeteo->forecast($coordinates)),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Traffic;

use App\Geo\Coordinates;
use App\Geo\GeocoderInterface;
use App\Geo\GeocodingException;
use App\Traffic\Waze\AutocompleteResponse;
use App\Traffic\Waze\RouteResult;
use App\Traffic\Waze\RoutingResponse;
use App\Traffic\Waze\WazeRequestException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client de l'API non officielle utilisée par la carte en ligne de Waze.
 *
 * Waze bloque les requêtes dont l'empreinte TLS n'est pas celle d'un navigateur récent :
 * en production, PHP est lancé avec libcurl-impersonate (voir le Dockerfile).
 */
class WazeClient implements TravelTimeProviderInterface, GeocoderInterface
{
    private const string ROUTING_URL = 'https://routing-livemap-row.waze.com/RoutingManager/routingRequest';
    private const string AUTOCOMPLETE_URL = 'https://gapi.waze.com/autocomplete/q';
    private const int ALTERNATIVES = 3;

    public function __construct(
        #[Target('waze.client')]
        private readonly HttpClientInterface $wazeClient,
        private readonly SerializerInterface $serializer,
    ) {
    }

    public function routes(Coordinates $from, Coordinates $to, bool $avoidTolls = false): array
    {
        try {
            $response = $this->fetch(self::ROUTING_URL, RoutingResponse::class, [
                'from' => \sprintf('x:%F y:%F', $from->longitude, $from->latitude),
                'to' => \sprintf('x:%F y:%F', $to->longitude, $to->latitude),
                'at' => 0,
                'returnJSON' => 'true',
                'returnGeometries' => 'false',
                'returnInstructions' => 'false',
                'timeout' => 60000,
                'nPaths' => self::ALTERNATIVES,
                'options' => 'AVOID_TRAILS:t,AVOID_TOLL_ROADS:'.($avoidTolls ? 't' : 'f').',AVOID_FERRIES:f',
                'subscription' => '*',
            ]);
        } catch (WazeRequestException $e) {
            throw new TrafficException($e->getMessage(), previous: $e);
        }

        if (null !== $response->error) {
            throw new TrafficException('Waze a renvoyé une erreur : '.$response->error);
        }

        $routes = [];
        foreach ($response->routes() as $result) {
            $route = self::route($result);
            // Waze renvoie parfois deux variantes d'un même itinéraire : on garde la plus rapide
            if (!isset($routes[$route->name]) || $route->minutes < $routes[$route->name]->minutes) {
                $routes[$route->name] = $route;
            }
        }

        if ([] === $routes) {
            throw new TrafficException('Waze n\'a renvoyé aucun itinéraire.');
        }

        usort($routes, static fn (Route $a, Route $b) => $a->minutes <=> $b->minutes);

        return $routes;
    }

    public function geocode(string $address, ?Coordinates $near = null): Coordinates
    {
        $query = ['q' => $address, 'e' => 'ROW', 'c' => 'wd', 'exp' => 8, 'gxy' => 1, 'lang' => 'fr'];
        if (null !== $near) {
            $query['sll'] = \sprintf('%F,%F', $near->latitude, $near->longitude);
        }

        try {
            $coordinates = $this->fetch(self::AUTOCOMPLETE_URL, AutocompleteResponse::class, $query)->firstCoordinates();
        } catch (WazeRequestException $e) {
            throw new GeocodingException($e->getMessage(), previous: $e);
        }

        return $coordinates ?? throw new GeocodingException(\sprintf('Adresse introuvable : "%s".', $address));
    }

    /**
     * @template T of object
     *
     * @param class-string<T>                 $type
     * @param array<string, string|int|float> $query
     *
     * @return T
     *
     * @throws WazeRequestException
     */
    private function fetch(string $url, string $type, array $query): object
    {
        try {
            $json = $this->wazeClient->request('GET', $url, ['query' => $query])->getContent();

            $result = $this->serializer->deserialize($json, $type, 'json');
        } catch (HttpExceptionInterface $e) {
            throw new WazeRequestException(\sprintf('Waze a refusé la requête (HTTP %d).', $e->getResponse()->getStatusCode()), previous: $e);
        } catch (HttpException $e) {
            throw new WazeRequestException('Waze est injoignable : '.$e->getMessage(), previous: $e);
        } catch (SerializerException $e) {
            throw new WazeRequestException('Réponse inattendue de Waze : '.$e->getMessage(), previous: $e);
        }

        if (!$result instanceof $type) {
            throw new WazeRequestException(\sprintf('Réponse inattendue de Waze : %s attendu.', $type));
        }

        return $result;
    }

    private static function route(RouteResult $result): Route
    {
        return new Route(
            name: $result->routeName,
            minutes: $result->seconds() / 60,
            freeFlowMinutes: $result->seconds(realTime: false) / 60,
            kilometers: $result->meters() / 1000,
        );
    }
}

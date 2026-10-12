<?php

declare(strict_types=1);

namespace App\Traffic\Waze;

/**
 * Réponse du calcul d'itinéraire : soit des alternatives, soit un itinéraire unique, soit une erreur.
 */
final readonly class RoutingResponse
{
    /**
     * @param list<RoutingAlternative>|null $alternatives
     */
    public function __construct(
        public ?array $alternatives = null,
        public ?RouteResult $response = null,
        public ?string $error = null,
    ) {
    }

    /**
     * @return list<RouteResult>
     */
    public function routes(): array
    {
        if (null !== $this->alternatives) {
            return array_map(static fn (RoutingAlternative $alternative) => $alternative->response, $this->alternatives);
        }

        return null !== $this->response ? [$this->response] : [];
    }
}

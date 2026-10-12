<?php

declare(strict_types=1);

namespace App\Traffic\Waze;

/**
 * Échec d'un appel à Waze (réseau, blocage ou réponse illisible).
 */
class WazeRequestException extends \RuntimeException
{
}

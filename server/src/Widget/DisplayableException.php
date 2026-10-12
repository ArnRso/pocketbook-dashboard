<?php

declare(strict_types=1);

namespace App\Widget;

/**
 * Exception dont le message peut être montré tel quel sur l'écran à la place du widget.
 */
interface DisplayableException extends \Throwable
{
}

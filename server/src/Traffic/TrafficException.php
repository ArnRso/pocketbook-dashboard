<?php

declare(strict_types=1);

namespace App\Traffic;

use App\Widget\DisplayableException;

class TrafficException extends \RuntimeException implements DisplayableException
{
}

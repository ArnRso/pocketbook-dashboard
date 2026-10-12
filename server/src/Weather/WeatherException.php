<?php

declare(strict_types=1);

namespace App\Weather;

use App\Widget\DisplayableException;

class WeatherException extends \RuntimeException implements DisplayableException
{
}

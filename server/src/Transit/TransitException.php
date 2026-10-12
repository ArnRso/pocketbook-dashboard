<?php

declare(strict_types=1);

namespace App\Transit;

use App\Widget\DisplayableException;

class TransitException extends \RuntimeException implements DisplayableException
{
}

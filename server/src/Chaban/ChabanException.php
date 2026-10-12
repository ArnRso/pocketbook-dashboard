<?php

declare(strict_types=1);

namespace App\Chaban;

use App\Widget\DisplayableException;

class ChabanException extends \RuntimeException implements DisplayableException
{
}

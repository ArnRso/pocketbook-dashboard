<?php

declare(strict_types=1);

namespace App\Widget;

final class CacheKey
{
    public static function of(string $prefix, float|int ...$values): string
    {
        return $prefix.'_'.str_replace('.', '_', implode('_', $values));
    }
}

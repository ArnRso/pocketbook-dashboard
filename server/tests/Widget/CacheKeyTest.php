<?php

declare(strict_types=1);

namespace App\Tests\Widget;

use App\Widget\CacheKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheKey::class)]
final class CacheKeyTest extends TestCase
{
    public function testKeyIsSafeForEveryCacheAdapter(): void
    {
        self::assertSame('routes_45_7576_-4_832_1', CacheKey::of('routes', 45.7576, -4.832, 1));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Traffic\Waze;

use App\Traffic\Waze\AutocompleteResponse;
use App\Traffic\Waze\AutocompleteResponseDenormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AutocompleteResponseDenormalizer::class)]
final class AutocompleteResponseDenormalizerTest extends TestCase
{
    public function testOnlyHandlesAutocompleteResponses(): void
    {
        $denormalizer = new AutocompleteResponseDenormalizer();

        self::assertSame([AutocompleteResponse::class => true], $denormalizer->getSupportedTypes('json'));
        self::assertTrue($denormalizer->supportsDenormalization([], AutocompleteResponse::class));
        self::assertFalse($denormalizer->supportsDenormalization([], \stdClass::class));
    }
}

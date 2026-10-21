<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\SecretGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

#[CoversClass(SecretGenerator::class)]
final class SecretGeneratorTest extends TestCase
{
    public function testPasswordIsLongAndUnambiguous(): void
    {
        $password = new SecretGenerator()->password();

        self::assertMatchesRegularExpression('/^[a-km-zA-HJ-NP-Z2-9]{20}$/', $password);
    }

    public function testPasswordUsesTheWholeAlphabet(): void
    {
        $generator = new SecretGenerator(new Randomizer(new Mt19937(42)));

        $characters = count_chars(implode('', array_map($generator->password(...), range(1, 200))), 3);

        self::assertSame(count_chars(SecretGenerator::PASSWORD_ALPHABET, 3), $characters);
    }

    public function testTokensAreUnique(): void
    {
        $generator = new SecretGenerator();
        $token = $generator->token();

        self::assertMatchesRegularExpression('/^pbd_[0-9a-f]{48}$/', $token);
        self::assertNotSame($token, $generator->token());
    }
}

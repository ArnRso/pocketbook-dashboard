<?php

declare(strict_types=1);

namespace App\Security;

use Random\Randomizer;

class SecretGenerator
{
    // Sans caractères ambigus (0/O, 1/l/I) pour pouvoir recopier le mot de passe à la main
    public const PASSWORD_ALPHABET = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    public const PASSWORD_LENGTH = 20;
    public const TOKEN_PREFIX = 'pbd_';
    public const TOKEN_BYTES = 24;

    public function __construct(
        private readonly Randomizer $randomizer = new Randomizer(),
    ) {
    }

    public function password(): string
    {
        return $this->randomizer->getBytesFromString(self::PASSWORD_ALPHABET, self::PASSWORD_LENGTH);
    }

    public function token(): string
    {
        return self::TOKEN_PREFIX.bin2hex($this->randomizer->getBytes(self::TOKEN_BYTES));
    }
}

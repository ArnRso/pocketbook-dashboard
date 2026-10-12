<?php

declare(strict_types=1);

namespace App\Security;

use App\Device\DeviceManager;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Authentifie les liseuses via l'en-tête « Authorization: Bearer <clé> ».
 */
class DeviceAccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private readonly DeviceManager $devices,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $device = $this->devices->authenticate($accessToken) ?? throw new BadCredentialsException('Clé d\'accès invalide.');

        return new UserBadge($device->getUserIdentifier(), static fn () => $device);
    }
}

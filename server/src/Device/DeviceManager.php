<?php

declare(strict_types=1);

namespace App\Device;

use App\Entity\Device;
use App\Repository\DeviceRepository;
use App\Security\SecretGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

class DeviceManager
{
    public function __construct(
        private readonly DeviceRepository $devices,
        private readonly EntityManagerInterface $entityManager,
        private readonly SecretGenerator $secrets,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Attribue une nouvelle clé à la liseuse ; l'ancienne cesse immédiatement de fonctionner.
     *
     * @return string la clé en clair, qui n'est stockée nulle part
     */
    public function generateToken(Device $device): string
    {
        $token = $this->secrets->token();
        $this->assignToken($device, $token);

        return $token;
    }

    /**
     * Attribue une clé connue à la liseuse (jeux de données de développement).
     */
    public function assignToken(Device $device, #[\SensitiveParameter] string $token): void
    {
        $device->setTokenHash(self::hash($token));
        $this->entityManager->flush();
    }

    /**
     * Retrouve la liseuse active correspondant à la clé et note sa dernière connexion.
     */
    public function authenticate(string $token): ?Device
    {
        $device = $this->devices->findOneBy(['tokenHash' => self::hash($token), 'enabled' => true]);
        if (null === $device) {
            return null;
        }

        $device->setLastSeenAt($this->clock->now());
        $this->entityManager->flush();

        return $device;
    }

    /**
     * Enregistre l'état transmis par la liseuse avec sa demande d'écran.
     */
    public function report(Device $device, DeviceReport $report): void
    {
        if (null === $report->battery) {
            return;
        }

        $device->reportBattery($report->battery, $report->charging);
        $this->entityManager->flush();
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}

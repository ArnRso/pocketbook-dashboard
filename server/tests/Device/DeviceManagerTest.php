<?php

declare(strict_types=1);

namespace App\Tests\Device;

use App\Device\DeviceManager;
use App\Entity\Device;
use App\Repository\DeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

#[CoversClass(DeviceManager::class)]
#[CoversClass(Device::class)]
final class DeviceManagerTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testGeneratedTokenAuthenticatesTheDevice(): void
    {
        $clock = self::mockTime('2026-10-12 13:20:00');
        $device = $this->createDevice();
        $manager = self::getContainer()->get(DeviceManager::class);

        $token = $manager->generateToken($device);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::assertTrue($device->hasToken());
        self::assertSame(hash('sha256', $token), $device->getTokenHash(), 'Seule l\'empreinte de la clé est stockée.');
        $authenticated = $manager->authenticate($token);
        self::assertSame($device->getId(), $authenticated?->getId());
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertEquals($clock->now(), self::getContainer()->get(DeviceRepository::class)->find($device->getId())?->getLastSeenAt());
        self::assertSame(['ROLE_DEVICE'], $device->getRoles());
        self::assertSame('device:'.$device->getId(), $device->getUserIdentifier());
    }

    public function testUnknownTokenIsRejected(): void
    {
        $this->createDevice();

        self::assertNull(self::getContainer()->get(DeviceManager::class)->authenticate('pbd_inconnue'));
    }

    public function testRegeneratingRevokesThePreviousToken(): void
    {
        $device = $this->createDevice();
        $manager = self::getContainer()->get(DeviceManager::class);

        $old = $manager->generateToken($device);
        $new = $manager->generateToken($device);

        self::assertNull($manager->authenticate($old));
        self::assertSame($device, $manager->authenticate($new));
    }

    public function testDisabledDeviceCannotAuthenticate(): void
    {
        $device = $this->createDevice();
        $manager = self::getContainer()->get(DeviceManager::class);
        $token = $manager->generateToken($device);

        $device->setEnabled(false);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        self::assertNull($manager->authenticate($token));
        self::assertNull($device->getLastSeenAt());
    }

    public function testAssignedTokenIsUsable(): void
    {
        $device = $this->createDevice();
        $manager = self::getContainer()->get(DeviceManager::class);

        $manager->assignToken($device, 'pbd_connue');
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::assertSame($device->getId(), $manager->authenticate('pbd_connue')?->getId());
    }

    private function createDevice(): Device
    {
        $device = new Device()->setName('Salon');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($device);
        $entityManager->flush();

        return $device;
    }
}

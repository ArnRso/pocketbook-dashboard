<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\DataFixtures\AppFixtures;
use App\Device\DeviceManager;
use App\Repository\PlaceRepository;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(AppFixtures::class)]
final class AppFixturesTest extends KernelTestCase
{
    public function testDemoDataIsUsable(): void
    {
        $container = self::getContainer();
        (new AppFixtures($container->get(DeviceManager::class)))->load($container->get(EntityManagerInterface::class));

        self::assertSame('Maison', $container->get(PlaceRepository::class)->findWeatherLocation()?->getLabel());
        // Du lundi 12 au dimanche 18 octobre 2026 : les deux trajets sont actifs tous les jours
        foreach (range(12, 18) as $day) {
            $trips = $container->get(TripRepository::class);
            self::assertSame('Maison → Travail', (string) $trips->findActiveAt(new \DateTimeImmutable("2026-10-{$day} 08:00")));
            self::assertSame('Travail → Maison', (string) $trips->findActiveAt(new \DateTimeImmutable("2026-10-{$day} 18:00")));
        }
        self::assertNotNull($container->get(DeviceManager::class)->authenticate(AppFixtures::DEVICE_TOKEN));
    }
}

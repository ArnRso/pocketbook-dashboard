<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\DataFixtures\AppFixtures;
use App\Device\DeviceManager;
use App\Entity\Tile\Tile;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(AppFixtures::class)]
final class AppFixturesTest extends KernelTestCase
{
    public function testDemoDataIsUsable(): void
    {
        $container = self::getContainer();
        (new AppFixtures($container->get(DeviceManager::class)))->load($container->get(EntityManagerInterface::class));

        $device = $container->get(DeviceManager::class)->authenticate(AppFixtures::DEVICE_TOKEN);
        $dashboard = $device?->getDashboard();
        self::assertNotNull($dashboard);
        self::assertSame('Principal', $dashboard->getName());

        // Du lundi 12 au dimanche 18 octobre 2026 : trajet aller le matin, retour l'après-midi
        foreach (range(12, 18) as $day) {
            self::assertSame(
                ['clock', 'current_weather', 'rain', 'hourly_forecast', 'commute'],
                array_map(static fn (Tile $tile): string => $tile->widget(), $dashboard->tilesVisibleAt(new \DateTimeImmutable("2026-10-{$day} 08:00"))),
            );
            self::assertContains('Trajet · Maison → Travail', self::descriptions($dashboard->tilesVisibleAt(new \DateTimeImmutable("2026-10-{$day} 08:00"))));
            self::assertContains('Trajet · Travail → Maison', self::descriptions($dashboard->tilesVisibleAt(new \DateTimeImmutable("2026-10-{$day} 18:00"))));
        }

        foreach ($dashboard->getTiles() as $tile) {
            self::assertCount(0, $container->get(ValidatorInterface::class)->validate($tile), $tile->describe());
        }
    }

    /**
     * @param list<Tile> $tiles
     *
     * @return list<string>
     */
    private static function descriptions(array $tiles): array
    {
        return array_map(static fn (Tile $tile): string => $tile->describe(), $tiles);
    }
}

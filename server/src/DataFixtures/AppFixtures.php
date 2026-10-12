<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Device\DeviceManager;
use App\Entity\Device;
use App\Entity\Place;
use App\Entity\Trip;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Données de démonstration pour le développement : bin/console doctrine:fixtures:load.
 */
final class AppFixtures extends Fixture
{
    // Clé de la liseuse de démonstration, à n'utiliser qu'en local
    public const DEVICE_TOKEN = 'pbd_dev000000000000000000000000000000000000000000000';

    public function __construct(
        private readonly DeviceManager $devices,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $home = (new Place())
            ->setLabel('Maison')
            ->setAddress('Place Bellecour, Lyon')
            ->setLatitude(45.75762)
            ->setLongitude(4.83202)
            ->setWeatherLocation(true);
        $work = (new Place())
            ->setLabel('Travail')
            ->setAddress('Villeurbanne')
            ->setLatitude(45.7719)
            ->setLongitude(4.8902);

        $morning = (new Trip())
            ->setOrigin($home)
            ->setDestination($work)
            ->setStartTime(new \DateTimeImmutable('00:00'))
            ->setEndTime(new \DateTimeImmutable('14:00'))
            ->setWeekdays([1, 2, 3, 4, 5, 6, 7]);
        $evening = (new Trip())
            ->setOrigin($work)
            ->setDestination($home)
            ->setStartTime(new \DateTimeImmutable('14:00'))
            ->setEndTime(new \DateTimeImmutable('23:45'))
            ->setWeekdays([1, 2, 3, 4, 5, 6, 7]);

        $device = (new Device())->setName('Liseuse de démonstration');

        foreach ([$home, $work, $morning, $evening, $device] as $entity) {
            $manager->persist($entity);
        }
        $manager->flush();

        $this->devices->assignToken($device, self::DEVICE_TOKEN);
    }
}

<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Device\DeviceManager;
use App\Entity\Dashboard;
use App\Entity\Device;
use App\Entity\Place;
use App\Entity\Tile\ClockTile;
use App\Entity\Tile\CommuteTile;
use App\Entity\Tile\CurrentWeatherTile;
use App\Entity\Tile\HourlyForecastTile;
use App\Entity\Tile\RainTile;
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
            ->setLongitude(4.83202);
        $work = (new Place())
            ->setLabel('Travail')
            ->setAddress('Villeurbanne')
            ->setLatitude(45.7719)
            ->setLongitude(4.8902);
        $toWork = (new Trip())->setOrigin($home)->setDestination($work);
        $toHome = (new Trip())->setOrigin($work)->setDestination($home);

        // Le trajet aller le matin et le retour l'après-midi partagent le même emplacement
        $morning = (new CommuteTile())->setTrip($toWork)->place(1, 12, 12, 5);
        $morning->getSchedule()->setStartTime(new \DateTimeImmutable('00:00'))->setEndTime(new \DateTimeImmutable('14:00'));
        $evening = (new CommuteTile())->setTrip($toHome)->place(1, 12, 12, 5);
        $evening->getSchedule()->setStartTime(new \DateTimeImmutable('14:00'))->setEndTime(new \DateTimeImmutable('23:45'));

        $dashboard = (new Dashboard())
            ->setName('Principal')
            ->addTile((new ClockTile())->place(1, 1, 12, 2))
            ->addTile((new CurrentWeatherTile())->setPlace($home)->place(1, 3, 12, 3))
            ->addTile((new RainTile())->setPlace($home)->place(1, 6, 12, 2))
            ->addTile((new HourlyForecastTile())->setPlace($home)->place(1, 8, 12, 4))
            ->addTile($morning)
            ->addTile($evening);

        $device = (new Device())->setName('Liseuse de démonstration')->setDashboard($dashboard);

        $manager->persist($home);
        $manager->persist($work);
        $manager->persist($toWork);
        $manager->persist($toHome);
        $manager->persist($dashboard);
        $manager->persist($device);
        $manager->flush();

        $this->devices->assignToken($device, self::DEVICE_TOKEN);
    }
}

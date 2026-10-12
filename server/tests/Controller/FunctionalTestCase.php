<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Device\DeviceManager;
use App\Entity\Device;
use App\Entity\Place;
use App\Entity\Trip;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Remplace les API externes (Open-Meteo, Waze, Gotenberg) par des réponses enregistrées.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    use ClockSensitiveTrait;

    protected KernelBrowser $client;

    /** Réponses des faux services : un test peut les remplacer pour simuler une panne */
    protected MockResponse $weatherResponse;
    protected MockResponse $routingResponse;
    protected MockResponse $autocompleteResponse;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        self::mockTime('2026-10-12 13:20:00');

        $this->weatherResponse = self::fixture('open-meteo-forecast.json');
        $this->routingResponse = self::fixture('waze-routes.json');
        $this->autocompleteResponse = self::fixture('waze-autocomplete.json');

        $container = static::getContainer();
        $container->set('open_meteo.client', new MockHttpClient(fn (): MockResponse => $this->weatherResponse, 'https://api.open-meteo.com'));
        $container->set('waze.client', new MockHttpClient(fn (string $method, string $url): MockResponse => str_contains($url, '/autocomplete/')
            ? $this->autocompleteResponse
            : $this->routingResponse,
        ));
        $container->set('gotenberg.client', new MockHttpClient(static fn (string $method, string $url): MockResponse => str_ends_with($url, '/version')
            ? new MockResponse('8.25.0')
            : new MockResponse(self::screenshot(), ['response_headers' => ['Content-Type' => 'image/png']]),
        ));
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function logIn(): void
    {
        $user = (new User('admin'))->setPassword('irrelevant');
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();
        $this->client->loginUser($user);
    }

    /**
     * @return array{Device, string} la liseuse et sa clé en clair
     */
    protected function createDevice(bool $enabled = true): array
    {
        $device = (new Device())->setName('Salon')->setRefreshSeconds(600)->setEnabled($enabled);
        $this->entityManager()->persist($device);
        $this->entityManager()->flush();

        return [$device, static::getContainer()->get(DeviceManager::class)->generateToken($device)];
    }

    protected function createCommute(bool $withCoordinates = true): Trip
    {
        $home = (new Place())->setLabel('Maison')->setAddress('Place Bellecour, Lyon')->setLatitude(45.7576)->setLongitude(4.832)->setWeatherLocation(true);
        $work = (new Place())->setLabel('Travail')->setAddress('Villeurbanne');
        if ($withCoordinates) {
            $work->setLatitude(45.7719)->setLongitude(4.8902);
        }
        $trip = (new Trip())
            ->setOrigin($home)
            ->setDestination($work)
            ->setStartTime(new \DateTimeImmutable('06:00'))
            ->setEndTime(new \DateTimeImmutable('20:00'));

        foreach ([$home, $work, $trip] as $entity) {
            $this->entityManager()->persist($entity);
        }
        $this->entityManager()->flush();

        return $trip;
    }

    protected function bearer(string $token): string
    {
        return 'Bearer '.$token;
    }

    private static function fixture(string $name): MockResponse
    {
        $json = file_get_contents(__DIR__.'/../fixtures/'.$name);
        self::assertIsString($json);

        return new MockResponse($json);
    }

    /**
     * Capture d'écran factice, en couleur, comme celle que renverrait Chromium.
     */
    private static function screenshot(): string
    {
        $image = new \Imagick();
        $image->newImage(1404, 1872, 'rgb(255, 200, 120)');
        $image->setImageFormat('png');

        return $image->getImageBlob();
    }
}

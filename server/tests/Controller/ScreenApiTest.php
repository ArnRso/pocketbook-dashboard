<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Api\ScreenController;
use App\Device\DeviceManager;
use App\Device\DeviceReport;
use App\Entity\Device;
use App\Repository\DeviceRepository;
use App\Screen\ScreenRenderer;
use App\Security\DeviceAccessTokenHandler;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ScreenController::class)]
#[CoversClass(DeviceManager::class)]
#[CoversClass(DeviceReport::class)]
#[CoversClass(ScreenRenderer::class)]
#[CoversClass(DeviceAccessTokenHandler::class)]
final class ScreenApiTest extends FunctionalTestCase
{
    public function testDeviceReceivesAGrayscaleScreen(): void
    {
        [$device, $token] = $this->createDevice(dashboard: $this->createDashboard());

        $this->client->request('GET', '/api/v1/screen', server: ['HTTP_AUTHORIZATION' => $this->bearer($token)]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertResponseHeaderSame('X-Refresh-Interval', '600');
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertNotNull($device->getLastSeenAt());

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        $image = new \Imagick();
        $image->readImageBlob($content);
        self::assertSame([1404, 1872], [$image->getImageWidth(), $image->getImageHeight()]);
        self::assertSame(\Imagick::COLORSPACE_GRAY, $image->getImageColorspace());
        self::assertLessThanOrEqual(16, $image->getImageColors());
    }

    public function testBatteryLevelIsRecorded(): void
    {
        [$device, $token] = $this->createDevice(dashboard: $this->createDashboard());

        $this->client->request('GET', '/api/v1/screen?battery=87&charging=1', server: ['HTTP_AUTHORIZATION' => $this->bearer($token)]);

        self::assertResponseIsSuccessful();
        self::assertSame([87, true], $this->battery($device));

        // Une ancienne version de l'application ne transmet rien : le dernier relevé est conservé
        $this->client->request('GET', '/api/v1/screen', server: ['HTTP_AUTHORIZATION' => $this->bearer($token)]);

        self::assertResponseIsSuccessful();
        self::assertSame([87, true], $this->battery($device));

        $this->client->request('GET', '/api/v1/screen?battery=86', server: ['HTTP_AUTHORIZATION' => $this->bearer($token)]);

        self::assertResponseIsSuccessful();
        self::assertSame([86, false], $this->battery($device));
    }

    public function testInvalidBatteryLevelIsRejected(): void
    {
        [$device, $token] = $this->createDevice(dashboard: $this->createDashboard());

        $this->client->request('GET', '/api/v1/screen?battery=150', server: ['HTTP_AUTHORIZATION' => $this->bearer($token)]);

        self::assertResponseStatusCodeSame(400);
        self::assertSame([null, false], $this->battery($device));
    }

    /**
     * @return array{?int, bool} niveau de batterie et charge enregistrés en base
     */
    private function battery(Device $device): array
    {
        $this->entityManager()->clear();
        $saved = static::getContainer()->get(DeviceRepository::class)->find($device->getId());
        self::assertInstanceOf(Device::class, $saved);

        return [$saved->getBatteryLevel(), $saved->isCharging()];
    }

    public function testDeviceWithoutDashboardStillReceivesAScreen(): void
    {
        [, $token] = $this->createDevice();

        $this->client->request('GET', '/api/v1/screen', server: ['HTTP_AUTHORIZATION' => $this->bearer($token)]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
    }

    public function testMissingTokenIsRejected(): void
    {
        $this->client->request('GET', '/api/v1/screen');

        self::assertResponseStatusCodeSame(401);
    }

    public function testInvalidTokenIsRejected(): void
    {
        $this->client->request('GET', '/api/v1/screen', server: ['HTTP_AUTHORIZATION' => $this->bearer('pbd_inconnue')]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testDisabledDeviceIsRejected(): void
    {
        [, $token] = $this->createDevice(enabled: false);

        $this->client->request('GET', '/api/v1/screen', server: ['HTTP_AUTHORIZATION' => $this->bearer($token)]);

        self::assertResponseStatusCodeSame(401);
    }
}

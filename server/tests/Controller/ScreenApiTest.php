<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Api\ScreenController;
use App\Screen\ScreenRenderer;
use App\Security\DeviceAccessTokenHandler;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ScreenController::class)]
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

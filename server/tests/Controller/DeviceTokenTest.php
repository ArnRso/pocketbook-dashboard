<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Admin\DeviceCrudController;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DeviceCrudController::class)]
final class DeviceTokenTest extends FunctionalTestCase
{
    public function testAdminGeneratesATokenThatTheDeviceCanUse(): void
    {
        $this->logIn();
        [$device, $oldToken] = $this->createDevice();

        $this->client->request('GET', \sprintf('/admin/device/%d/token', (int) $device->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'désactive immédiatement l\'ancienne');

        $crawler = $this->client->submitForm('Générer une clé');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert-success');
        $token = $crawler->filter('code')->last()->text();
        self::assertMatchesRegularExpression('/^pbd_[0-9a-f]{48}$/', $token);

        $this->client->request('GET', '/api/v1/screen', server: ['HTTP_AUTHORIZATION' => $this->bearer($token)]);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/v1/screen', server: ['HTTP_AUTHORIZATION' => $this->bearer($oldToken)]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testDeviceWithoutTokenIsExplained(): void
    {
        $this->logIn();
        [$device] = $this->createDevice();
        $device->setTokenHash(null);
        $this->entityManager()->flush();

        $this->client->request('GET', \sprintf('/admin/device/%d/token', (int) $device->getId()));

        self::assertSelectorTextContains('body', 'n\'a pas encore de clé');
    }

    public function testTokenIsNotGeneratedWithoutAValidCsrfToken(): void
    {
        $this->logIn();
        [$device, $token] = $this->createDevice();

        $this->client->request('POST', \sprintf('/admin/device/%d/token', (int) $device->getId()), ['_token' => 'invalide']);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.alert-success');
        $this->client->request('GET', '/api/v1/screen', server: ['HTTP_AUTHORIZATION' => $this->bearer($token)]);
        self::assertResponseIsSuccessful();
    }
}

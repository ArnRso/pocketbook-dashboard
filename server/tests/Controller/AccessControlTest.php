<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\DashboardController;
use App\Controller\HealthController;
use App\Controller\SecurityController;
use App\Security\DeviceAccessTokenHandler;
use App\User\UserManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(SecurityController::class)]
#[CoversClass(DashboardController::class)]
#[CoversClass(HealthController::class)]
#[CoversClass(DeviceAccessTokenHandler::class)]
final class AccessControlTest extends FunctionalTestCase
{
    #[DataProvider('protectedPages')]
    public function testAnonymousVisitorIsRedirectedToLogin(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseRedirects('/login');
    }

    /**
     * @return iterable<array{string}>
     */
    public static function protectedPages(): iterable
    {
        yield ['/'];
        yield ['/admin'];
        yield ['/admin/device'];
        yield ['/preview'];
        yield ['/preview.png'];
    }

    public function testLoginPageIsPublic(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_username"]');
        self::assertSelectorExists('input[name="_password"]');
    }

    public function testHealthCheckIsPublic(): void
    {
        $this->client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertSame('OK', $this->client->getResponse()->getContent());
    }

    public function testLoginWithWrongPasswordFails(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', ['_username' => 'inconnu', '_password' => 'faux']);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
    }

    public function testDeviceTokenDoesNotOpenTheAdmin(): void
    {
        [, $token] = $this->createDevice();

        $this->client->request('GET', '/admin', server: ['HTTP_AUTHORIZATION' => $this->bearer($token)]);

        self::assertResponseRedirects('/login');
    }

    public function testUserSessionDoesNotOpenTheApi(): void
    {
        $password = static::getContainer()->get(UserManager::class)->createOrResetPassword('alice');
        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', ['_username' => 'alice', '_password' => $password]);
        self::assertResponseRedirects('/admin');

        $this->client->request('GET', '/api/v1/screen');

        self::assertResponseStatusCodeSame(401);
    }

    public function testHomeRedirectsToTheAdmin(): void
    {
        $this->logIn();

        $this->client->request('GET', '/');

        self::assertResponseRedirects('/admin');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CreateUserCommand;
use App\User\UserManager;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CreateUserCommand::class)]
final class CreateUserCommandTest extends KernelTestCase
{
    public function testCreatesUserAndDisplaysPassword(): void
    {
        $tester = $this->tester();

        $tester->execute(['username' => 'bob']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Identifiant : bob', $tester->getDisplay());
        self::assertMatchesRegularExpression('/Mot de passe : [a-zA-Z0-9]{20}\n/', $tester->getDisplay());
        self::assertStringNotContainsString('existe déjà', $tester->getDisplay());
        self::assertStringContainsString('Ce mot de passe n\'est affiché qu\'une seule fois.', $tester->getDisplay());
        self::assertTrue(self::getContainer()->get(UserManager::class)->exists('bob'));
    }

    public function testExistingUserIsLeftUntouchedWithoutConfirmation(): void
    {
        self::getContainer()->get(UserManager::class)->createOrResetPassword('bob');
        $tester = $this->tester();

        $tester->setInputs(['no']);
        $tester->execute(['username' => 'bob']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('L\'utilisateur "bob" existe déjà', $tester->getDisplay());
        self::assertStringNotContainsString('Mot de passe :', $tester->getDisplay());
    }

    public function testConfirmationDefaultsToNo(): void
    {
        self::getContainer()->get(UserManager::class)->createOrResetPassword('bob');
        $tester = $this->tester();

        $tester->setInputs(['']);
        $tester->execute(['username' => 'bob']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testExistingUserGetsANewPasswordAfterConfirmation(): void
    {
        $old = self::getContainer()->get(UserManager::class)->createOrResetPassword('bob');
        $tester = $this->tester();

        $tester->setInputs(['yes']);
        $tester->execute(['username' => 'bob']);

        $tester->assertCommandIsSuccessful();
        self::assertMatchesRegularExpression('/Mot de passe : [a-zA-Z0-9]{20}\n/', $tester->getDisplay());
        self::assertStringNotContainsString($old, $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:user:create'));
    }
}

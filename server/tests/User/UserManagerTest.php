<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\Entity\User;
use App\Repository\UserRepository;
use App\User\UserManager;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[CoversClass(UserManager::class)]
#[CoversClass(User::class)]
final class UserManagerTest extends KernelTestCase
{
    public function testCreateThenResetPassword(): void
    {
        $manager = self::getContainer()->get(UserManager::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        self::assertFalse($manager->exists('alice'));

        $first = $manager->createOrResetPassword('alice');
        $user = $this->findUser('alice');

        self::assertTrue($manager->exists('alice'));
        self::assertSame('alice', $user->getUserIdentifier());
        self::assertSame(['ROLE_ADMIN'], $user->getRoles());
        self::assertNotSame($first, $user->getPassword(), 'Le mot de passe doit être haché.');
        self::assertTrue($hasher->isPasswordValid($user, $first));

        $second = $manager->createOrResetPassword('alice');

        self::assertNotSame($first, $second);
        self::assertCount(1, self::getContainer()->get(UserRepository::class)->findBy(['username' => 'alice']));
        self::assertFalse($hasher->isPasswordValid($user, $first));
        self::assertTrue($hasher->isPasswordValid($user, $second));
    }

    public function testOnlyTheRequestedUserIsReset(): void
    {
        $manager = self::getContainer()->get(UserManager::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $alicePassword = $manager->createOrResetPassword('alice');

        $bobPassword = $manager->createOrResetPassword('bob');

        self::assertTrue($hasher->isPasswordValid($this->findUser('alice'), $alicePassword));
        self::assertTrue($hasher->isPasswordValid($this->findUser('bob'), $bobPassword));
    }

    public function testExistsOnlyForTheGivenUsername(): void
    {
        self::getContainer()->get(UserManager::class)->createOrResetPassword('alice');

        self::assertFalse(self::getContainer()->get(UserManager::class)->exists('bob'));
    }

    public function testEmptyUsernameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::getContainer()->get(UserManager::class)->createOrResetPassword('');
    }

    public function testSessionNeverContainsThePasswordHash(): void
    {
        $user = new User('alice')->setPassword('$2y$13$hash');

        $serialized = serialize($user);

        self::assertStringNotContainsString('$2y$13$hash', $serialized);
        $restored = unserialize($serialized);
        self::assertInstanceOf(User::class, $restored);
        self::assertSame('alice', $restored->getUserIdentifier());
        self::assertSame(hash('crc32c', '$2y$13$hash'), $restored->getPassword());
    }

    private function findUser(string $username): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['username' => $username]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }
}

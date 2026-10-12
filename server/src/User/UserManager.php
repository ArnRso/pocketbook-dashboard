<?php

declare(strict_types=1);

namespace App\User;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\SecretGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManager
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SecretGenerator $secrets,
    ) {
    }

    public function exists(string $username): bool
    {
        return null !== $this->users->findOneBy(['username' => $username]);
    }

    /**
     * Crée l'utilisateur s'il n'existe pas, puis lui attribue un nouveau mot de passe.
     *
     * @return string le mot de passe en clair, qui n'est stocké nulle part
     */
    public function createOrResetPassword(string $username): string
    {
        if ('' === $username) {
            throw new \InvalidArgumentException('L\'identifiant ne peut pas être vide.');
        }

        $user = $this->users->findOneBy(['username' => $username]) ?? new User($username);
        $password = $this->secrets->password();

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $password;
    }
}

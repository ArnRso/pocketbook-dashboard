<?php

declare(strict_types=1);

namespace App\Command;

use App\User\UserManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:create',
    description: 'Crée un utilisateur avec un mot de passe généré, ou régénère le mot de passe d\'un utilisateur existant',
)]
final class CreateUserCommand
{
    public function __construct(
        private readonly UserManager $users,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Identifiant de connexion')] string $username): int
    {
        if ($this->users->exists($username)
            && !$io->confirm(\sprintf('L\'utilisateur "%s" existe déjà. Générer un nouveau mot de passe ?', $username), false)) {
            return Command::FAILURE;
        }

        $password = $this->users->createOrResetPassword($username);

        $io->success(\sprintf('Identifiant : %s', $username));
        $io->writeln(\sprintf('Mot de passe : <info>%s</info>', $password));
        $io->note('Ce mot de passe n\'est affiché qu\'une seule fois.');

        return Command::SUCCESS;
    }
}

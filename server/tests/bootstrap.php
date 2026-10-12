<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// Base de test SQLite recréée à chaque lancement ; DAMA isole ensuite chaque test dans une transaction
foreach (['doctrine:schema:drop --force --full-database', 'doctrine:migrations:migrate --no-interaction'] as $command) {
    passthru(sprintf('php "%s/bin/console" %s --env=test --quiet', dirname(__DIR__), $command), $exitCode);
    if (0 !== $exitCode) {
        exit($exitCode);
    }
}

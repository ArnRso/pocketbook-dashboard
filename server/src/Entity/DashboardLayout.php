<?php

declare(strict_types=1);

namespace App\Entity;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Mise en page d'un dashboard. Traduisible pour que les formulaires et l'admin affichent son libellé.
 */
enum DashboardLayout: string implements TranslatableInterface
{
    /** Les tuiles affichées s'empilent dans leur ordre et se partagent tout l'écran */
    case Auto = 'auto';

    /** Chaque tuile a sa place fixe sur la grille de 12 × 16 cases */
    case Grid = 'grid';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Automatique',
            self::Grid => 'Grille libre',
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->label();
    }
}

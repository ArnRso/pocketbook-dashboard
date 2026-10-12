<?php

declare(strict_types=1);

namespace App\Widget;

use App\Entity\Tile\Tile;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Affichage d'un type de tuile. Chaque widget se déclare sous le nom de sa tuile avec
 * #[AsTaggedItem(XxxTile::WIDGET)].
 */
#[AutoconfigureTag(self::TAG)]
interface WidgetInterface
{
    public const TAG = 'app.widget';

    public function template(): string;

    /**
     * Données passées au template sous le nom « data ».
     *
     * @param \DateTimeImmutable $at moment affiché : l'heure courante, ou celle choisie pour un aperçu
     *
     * @throws DisplayableException quand le widget ne peut pas être affiché pour une raison connue
     */
    public function data(Tile $tile, \DateTimeImmutable $at): object;
}

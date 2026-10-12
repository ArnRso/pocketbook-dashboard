<?php

declare(strict_types=1);

namespace App\Screen;

use App\Entity\Tile\TileWidth;

/**
 * Mise en page automatique : les tuiles affichées s'empilent dans leur ordre, deux tuiles en
 * demi-largeur qui se suivent partagent une ligne, et les lignes se partagent toute la hauteur de
 * l'écran selon la hauteur de leurs tuiles. Une tuile masquée ou vide libère donc sa place aux autres.
 */
final class AutoLayout
{
    /**
     * @param list<RenderedTile> $tiles dans l'ordre de la mise en page
     *
     * @return list<LayoutRow>
     */
    public function rows(array $tiles): array
    {
        $rows = [];
        $pending = null;
        foreach ($tiles as $tile) {
            if (TileWidth::Full === $tile->tile->getAutoWidth()) {
                if (null !== $pending) {
                    $rows[] = self::row($pending);
                    $pending = null;
                }
                $rows[] = self::row($tile);
            } elseif (null === $pending) {
                $pending = $tile;
            } else {
                $rows[] = self::row($pending, $tile);
                $pending = null;
            }
        }
        // Une tuile en demi-largeur restée seule prend toute la ligne
        if (null !== $pending) {
            $rows[] = self::row($pending);
        }

        return $rows;
    }

    private static function row(RenderedTile $first, RenderedTile ...$others): LayoutRow
    {
        $tiles = [$first, ...array_values($others)];

        return new LayoutRow($tiles, max(array_map(static fn (RenderedTile $tile): int => $tile->tile->getAutoSize()->value, $tiles)));
    }
}

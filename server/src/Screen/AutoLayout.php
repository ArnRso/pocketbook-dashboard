<?php

declare(strict_types=1);

namespace App\Screen;

use App\Entity\Tile\TileSize;
use App\Entity\Tile\TileWidth;

/**
 * Mise en page automatique : les tuiles affichées s'empilent dans leur ordre et occupent tout l'écran,
 * chacune le plus grande possible. Les tailles imposées sont respectées, les autres tuiles comblent les trous :
 *
 * - en largeur, une tuile prend toute la ligne, sauf pour compléter une tuile en demi-largeur qui la
 *   précède ; une tuile en demi-largeur restée seule prend toute la ligne ;
 * - en hauteur, les lignes de hauteur imposée sont servies d'abord, les autres se partagent le reste à
 *   parts égales (au moins la plus petite hauteur), sans dépasser la hauteur maximale de leurs widgets.
 *   Si toutes ont ce qu'il leur faut, le bas de l'écran reste libre ; si le total dépasse l'écran, toutes
 *   les lignes se réduisent en proportion.
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
        $groups = [];
        $pending = null;
        foreach ($tiles as $tile) {
            $width = $tile->tile->getAutoWidth();
            if (null !== $pending && TileWidth::Full !== $width) {
                $groups[] = [$pending, $tile];
                $pending = null;
                continue;
            }
            if (null !== $pending) {
                $groups[] = [$pending];
                $pending = null;
            }
            if (TileWidth::Half === $width) {
                $pending = $tile;
            } else {
                $groups[] = [$tile];
            }
        }
        if (null !== $pending) {
            $groups[] = [$pending];
        }

        return self::share($groups);
    }

    /**
     * @param list<non-empty-list<RenderedTile>> $groups
     *
     * @return list<LayoutRow>
     */
    private static function share(array $groups): array
    {
        $heights = [];
        /** @var array<int, float> $open hauteur maximale des lignes adaptatives pas encore servies */
        $open = [];
        $remaining = 1.0;
        foreach ($groups as $index => $group) {
            $fixed = self::fixedHeight($group);
            if (null === $fixed) {
                $open[$index] = self::maxHeight($group);
            } else {
                $heights[$index] = $fixed;
                $remaining -= $fixed;
            }
        }

        // Part égale pour les lignes adaptatives ; celles qui en ont besoin de moins la laissent aux autres
        while ([] !== $open) {
            $share = max(TileSize::Small->fraction(), $remaining / \count($open));
            $capped = array_filter($open, static fn (float $max): bool => $max < $share);
            if ([] === $capped) {
                $heights += array_fill_keys(array_keys($open), $share);
                break;
            }
            $heights += $capped;
            $remaining -= array_sum($capped);
            $open = array_diff_key($open, $capped);
        }
        ksort($heights);

        // Trop de hauteurs imposées pour l'écran : tout est réduit en proportion
        $scale = 1 / max(1.0, array_sum($heights));

        return array_map(static fn (array $group, float $height): LayoutRow => new LayoutRow($group, $height * $scale), $groups, array_values($heights));
    }

    /**
     * Hauteur imposée de la ligne : la plus grande de ses tuiles, null si aucune n'en impose.
     *
     * @param non-empty-list<RenderedTile> $group
     */
    private static function fixedHeight(array $group): ?float
    {
        $sizes = array_filter(array_map(static fn (RenderedTile $tile): ?TileSize => $tile->tile->getAutoSize(), $group), static fn (?TileSize $size): bool => null !== $size);

        return [] !== $sizes ? max(array_map(static fn (TileSize $size): float => $size->fraction(), $sizes)) : null;
    }

    /**
     * Hauteur dont la ligne a besoin au plus : celle de sa tuile la plus exigeante.
     *
     * @param non-empty-list<RenderedTile> $group
     */
    private static function maxHeight(array $group): float
    {
        return max(array_map(static fn (RenderedTile $tile): int => $tile->tile::maxHeight(), $group)) / Grid::ROWS;
    }
}

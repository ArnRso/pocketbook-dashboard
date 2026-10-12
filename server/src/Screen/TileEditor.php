<?php

declare(strict_types=1);

namespace App\Screen;

use App\Entity\Dashboard;
use App\Entity\Tile\Tile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Modifications des tuiles d'un dashboard depuis l'éditeur de grille. Une modification n'est
 * enregistrée que si la grille reste valide : tuiles dans la grille, sans chevauchement aux mêmes horaires.
 */
class TileEditor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @return list<TileTypeChoice>
     */
    public function types(): array
    {
        return array_map(
            static fn (string $widget, string $class): TileTypeChoice => new TileTypeChoice($widget, $class::label()),
            array_keys(Tile::TYPES),
            array_values(Tile::TYPES),
        );
    }

    /**
     * Nouvelle tuile du type demandé, rattachée à aucun dashboard tant qu'elle n'est pas ajoutée.
     *
     * @throws \InvalidArgumentException si le type est inconnu
     */
    public function create(string $widget): Tile
    {
        $class = Tile::TYPES[$widget] ?? throw new \InvalidArgumentException(\sprintf('Type de tuile inconnu : %s.', $widget));

        return new $class();
    }

    /**
     * Ajoute la tuile à la première place libre aux mêmes horaires, en lisant la grille de gauche à
     * droite puis de haut en bas.
     *
     * @throws TileLayoutException s'il n'y a plus de place pour la tuile
     */
    public function add(Dashboard $dashboard, Tile $tile): void
    {
        $dashboard->addTile($tile);
        $width = $tile->getWidth();
        $height = $tile->getHeight();

        for ($row = 1; $row + $height - 1 <= Grid::ROWS; ++$row) {
            for ($column = 1; $column + $width - 1 <= Grid::COLUMNS; ++$column) {
                $tile->place($column, $row, $width, $height);
                if (0 === \count($this->validator->validate($tile))) {
                    $this->entityManager->persist($tile);
                    $this->entityManager->flush();

                    return;
                }
            }
        }

        $dashboard->removeTile($tile);
        throw new TileLayoutException(\sprintf('Plus de place libre de %d × %d cases à ces horaires : libérez de la place ou changez les horaires.', $width, $height));
    }

    /**
     * @throws TileLayoutException si la tuile sortirait de la grille ou en chevaucherait une autre
     */
    public function move(Tile $tile, TileLayout $layout): void
    {
        $previous = new TileLayout($tile->getColumn(), $tile->getRow(), $tile->getWidth(), $tile->getHeight());
        $tile->place($layout->column, $layout->row, $layout->width, $layout->height);

        $violations = $this->validator->validate($tile);
        if (\count($violations) > 0) {
            $tile->place($previous->column, $previous->row, $previous->width, $previous->height);
            throw new TileLayoutException((string) $violations->get(0)->getMessage());
        }

        $this->entityManager->flush();
    }

    /**
     * Enregistre les réglages d'une tuile, déjà validés par son formulaire.
     */
    public function save(Tile $tile): void
    {
        $this->entityManager->flush();
    }

    public function remove(Tile $tile): void
    {
        $tile->getDashboard()?->removeTile($tile);
        $this->entityManager->remove($tile);
        $this->entityManager->flush();
    }
}

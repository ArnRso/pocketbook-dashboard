<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Tile\Tile;
use App\Repository\DashboardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Écran composé de tuiles, empilées automatiquement ou placées sur la grille ; chaque liseuse affiche le
 * dashboard qui lui est assigné.
 */
#[ORM\Entity(repositoryClass: DashboardRepository::class)]
#[UniqueEntity('name', message: 'Un dashboard porte déjà ce nom.')]
class Dashboard implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 8, enumType: DashboardLayout::class, options: ['default' => DashboardLayout::Auto->value])]
    private DashboardLayout $layout = DashboardLayout::Auto;

    /** @var Collection<int, Tile> */
    #[ORM\OneToMany(targetEntity: Tile::class, mappedBy: 'dashboard', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'row' => 'ASC', 'column' => 'ASC'])]
    private Collection $tiles;

    public function __construct()
    {
        $this->tiles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getLayout(): DashboardLayout
    {
        return $this->layout;
    }

    public function setLayout(DashboardLayout $layout): static
    {
        $this->layout = $layout;

        return $this;
    }

    /** @return Collection<int, Tile> */
    public function getTiles(): Collection
    {
        return $this->tiles;
    }

    /**
     * Ajoute la tuile en dernière position de la mise en page automatique.
     */
    public function addTile(Tile $tile): static
    {
        if (!$this->tiles->contains($tile)) {
            $last = $this->tiles->reduce(static fn (int $max, Tile $other): int => max($max, $other->getPosition()), 0);
            $this->tiles->add($tile);
            $tile->setDashboard($this)->setPosition($last + 1);
        }

        return $this;
    }

    /**
     * Tuiles dans l'ordre de la mise en page automatique.
     *
     * @return list<Tile>
     */
    public function orderedTiles(): array
    {
        $tiles = $this->tiles->toArray();
        usort($tiles, static fn (Tile $a, Tile $b): int => $a->getPosition() <=> $b->getPosition());

        return $tiles;
    }

    /**
     * Échange la tuile avec sa voisine, avant (-1) ou après (1), dans l'ordre de la mise en page automatique.
     */
    public function shiftTile(Tile $tile, int $direction): void
    {
        $tiles = $this->orderedTiles();
        $index = array_search($tile, $tiles, true);
        $neighbour = false !== $index ? $tiles[$index + $direction] ?? null : null;
        if (null === $neighbour) {
            return;
        }

        $tiles[$index] = $neighbour;
        $tiles[$index + $direction] = $tile;
        foreach ($tiles as $rank => $each) {
            $each->setPosition($rank + 1);
        }
    }

    public function removeTile(Tile $tile): static
    {
        $this->tiles->removeElement($tile);

        return $this;
    }

    /**
     * @return list<Tile> dans l'ordre de la mise en page automatique
     */
    public function tilesVisibleAt(\DateTimeImmutable $moment): array
    {
        return array_values(array_filter($this->orderedTiles(), static fn (Tile $tile): bool => $tile->isVisibleAt($moment)));
    }

    /**
     * @return list<Tile> dans l'ordre de la mise en page automatique
     */
    public function tilesHiddenAt(\DateTimeImmutable $moment): array
    {
        return array_values(array_filter($this->orderedTiles(), static fn (Tile $tile): bool => !$tile->isVisibleAt($moment)));
    }

    public function __toString(): string
    {
        return $this->name;
    }
}

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
 * Écran composé de tuiles placées sur la grille ; chaque liseuse affiche le dashboard qui lui est assigné.
 */
#[ORM\Entity(repositoryClass: DashboardRepository::class)]
#[UniqueEntity('name', message: 'Un dashboard porte déjà ce nom.')]
class Dashboard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    private string $name = '';

    /** @var Collection<int, Tile> */
    #[ORM\OneToMany(targetEntity: Tile::class, mappedBy: 'dashboard', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['row' => 'ASC', 'column' => 'ASC'])]
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

    /** @return Collection<int, Tile> */
    public function getTiles(): Collection
    {
        return $this->tiles;
    }

    public function addTile(Tile $tile): static
    {
        if (!$this->tiles->contains($tile)) {
            $this->tiles->add($tile);
            $tile->setDashboard($this);
        }

        return $this;
    }

    public function removeTile(Tile $tile): static
    {
        $this->tiles->removeElement($tile);

        return $this;
    }

    /**
     * @return list<Tile>
     */
    public function tilesVisibleAt(\DateTimeImmutable $moment): array
    {
        return array_values($this->tiles->filter(static fn (Tile $tile): bool => $tile->isVisibleAt($moment))->toArray());
    }

    public function __toString(): string
    {
        return $this->name;
    }
}

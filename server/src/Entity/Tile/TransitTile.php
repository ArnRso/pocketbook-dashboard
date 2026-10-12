<?php

declare(strict_types=1);

namespace App\Entity\Tile;

use App\Entity\TransitStop;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Prochains passages TBM à un ou plusieurs arrêts, chacun dans sa ligne et sa direction.
 */
#[ORM\Entity]
class TransitTile extends Tile
{
    public const WIDGET = 'transit';
    public const DEFAULT_DEPARTURES = 3;
    public const MAX_DEPARTURES = 6;

    /** @var Collection<int, TransitStop> */
    #[ORM\ManyToMany(targetEntity: TransitStop::class)]
    #[ORM\JoinTable(name: 'tile_transit_stop')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    #[Assert\Count(min: 1, minMessage: 'Choisis au moins un arrêt.')]
    private Collection $stops;

    /** Nombre de prochains passages affichés par arrêt */
    #[ORM\Column(nullable: true)]
    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: self::MAX_DEPARTURES)]
    private ?int $departures = self::DEFAULT_DEPARTURES;

    public function __construct()
    {
        parent::__construct();
        $this->stops = new ArrayCollection();
    }

    public function widget(): string
    {
        return self::WIDGET;
    }

    public static function label(): string
    {
        return 'Prochains passages TBM';
    }

    protected static function defaultWidth(): int
    {
        return 12;
    }

    protected static function defaultHeight(): int
    {
        return 3;
    }

    /**
     * Le titre, puis une ligne par arrêt.
     */
    public function maxHeight(): int
    {
        return max(2, 1 + $this->stops->count());
    }

    /** @return Collection<int, TransitStop> */
    public function getStops(): Collection
    {
        return $this->stops;
    }

    public function addStop(TransitStop $stop): static
    {
        if (!$this->stops->contains($stop)) {
            $this->stops->add($stop);
        }

        return $this;
    }

    public function removeStop(TransitStop $stop): static
    {
        $this->stops->removeElement($stop);

        return $this;
    }

    public function getDepartures(): ?int
    {
        return $this->departures;
    }

    public function setDepartures(?int $departures): static
    {
        $this->departures = $departures;

        return $this;
    }

    public function describe(): string
    {
        $names = array_unique(array_map(static fn (TransitStop $stop): string => $stop->getStopName(), $this->stops->toArray()));

        return [] !== $names ? \sprintf('%s · %s', static::label(), implode(', ', $names)) : static::label();
    }
}

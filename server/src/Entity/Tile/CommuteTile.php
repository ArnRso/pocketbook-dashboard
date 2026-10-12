<?php

declare(strict_types=1);

namespace App\Entity\Tile;

use App\Entity\Trip;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class CommuteTile extends Tile
{
    public const WIDGET = 'commute';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Choisis un trajet.')]
    private ?Trip $trip = null;

    public function widget(): string
    {
        return self::WIDGET;
    }

    public static function label(): string
    {
        return 'Trajet';
    }

    protected static function defaultWidth(): int
    {
        return 12;
    }

    protected static function defaultHeight(): int
    {
        return 5;
    }

    public function maxHeight(): int
    {
        return 3;
    }

    public function getTrip(): ?Trip
    {
        return $this->trip;
    }

    public function setTrip(?Trip $trip): static
    {
        $this->trip = $trip;

        return $this;
    }

    public function describe(): string
    {
        return \sprintf('%s · %s', static::label(), $this->trip);
    }
}

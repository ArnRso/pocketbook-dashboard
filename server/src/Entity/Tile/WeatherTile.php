<?php

declare(strict_types=1);

namespace App\Entity\Tile;

use App\Entity\Place;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tuile qui affiche la météo d'un lieu.
 */
#[ORM\Entity]
abstract class WeatherTile extends Tile
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Choisis un lieu.')]
    private ?Place $place = null;

    public function getPlace(): ?Place
    {
        return $this->place;
    }

    public function setPlace(?Place $place): static
    {
        $this->place = $place;

        return $this;
    }

    public function describe(): string
    {
        return \sprintf('%s · %s', static::label(), $this->place);
    }
}

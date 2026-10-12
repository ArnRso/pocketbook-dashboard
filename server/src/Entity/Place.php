<?php

declare(strict_types=1);

namespace App\Entity;

use App\Geo\Coordinates;
use App\Repository\PlaceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlaceRepository::class)]
class Place
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $label = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $address = '';

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: -90, max: 90)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: -180, max: 180)]
    private ?float $longitude = null;

    #[ORM\Column]
    private bool $weatherLocation = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getCoordinates(): ?Coordinates
    {
        if (null === $this->latitude || null === $this->longitude) {
            return null;
        }

        return new Coordinates($this->latitude, $this->longitude);
    }

    public function setCoordinates(Coordinates $coordinates): static
    {
        $this->latitude = $coordinates->latitude;
        $this->longitude = $coordinates->longitude;

        return $this;
    }

    public function isWeatherLocation(): bool
    {
        return $this->weatherLocation;
    }

    public function setWeatherLocation(bool $weatherLocation): static
    {
        $this->weatherLocation = $weatherLocation;

        return $this;
    }

    public function __toString(): string
    {
        return $this->label;
    }
}

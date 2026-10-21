<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TripRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Trajet dont une tuile affiche le temps de parcours ; ses horaires d'affichage sont ceux de la tuile.
 */
#[ORM\Entity(repositoryClass: TripRepository::class)]
class Trip implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Place $origin = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Place $destination = null;

    #[ORM\Column]
    private bool $avoidTolls = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrigin(): ?Place
    {
        return $this->origin;
    }

    public function setOrigin(?Place $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getDestination(): ?Place
    {
        return $this->destination;
    }

    public function setDestination(?Place $destination): static
    {
        $this->destination = $destination;

        return $this;
    }

    public function isAvoidTolls(): bool
    {
        return $this->avoidTolls;
    }

    public function setAvoidTolls(bool $avoidTolls): static
    {
        $this->avoidTolls = $avoidTolls;

        return $this;
    }

    #[Assert\Callback]
    public function validateDistinctPlaces(ExecutionContextInterface $context): void
    {
        if (null !== $this->origin && $this->origin === $this->destination) {
            $context->buildViolation('La destination doit être différente du départ.')
                ->atPath('destination')
                ->addViolation();
        }
    }

    public function __toString(): string
    {
        return \sprintf('%s → %s', $this->origin, $this->destination);
    }
}

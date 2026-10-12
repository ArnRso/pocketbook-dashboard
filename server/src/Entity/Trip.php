<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TripRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Trajet affiché sur le dashboard pendant sa plage horaire (ex. Maison → Travail de 6h à 10h).
 */
#[ORM\Entity(repositoryClass: TripRepository::class)]
class Trip
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

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startTime = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    #[Assert\NotNull]
    #[Assert\GreaterThan(propertyPath: 'startTime', message: "L'heure de fin doit être après l'heure de début.")]
    private ?\DateTimeImmutable $endTime = null;

    /** @var list<int> Jours ISO-8601 : 1 = lundi … 7 = dimanche */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Count(min: 1, minMessage: 'Choisis au moins un jour.')]
    private array $weekdays = [1, 2, 3, 4, 5];

    #[ORM\Column]
    private bool $avoidTolls = false;

    #[ORM\Column]
    private bool $enabled = true;

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

    public function getStartTime(): ?\DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeImmutable $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeImmutable $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    /** @return list<int> */
    public function getWeekdays(): array
    {
        return $this->weekdays;
    }

    /** @param list<int> $weekdays */
    public function setWeekdays(array $weekdays): static
    {
        $this->weekdays = $weekdays;

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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

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

    public function isActiveAt(\DateTimeImmutable $moment): bool
    {
        if (!$this->enabled || null === $this->startTime || null === $this->endTime
            || !\in_array((int) $moment->format('N'), $this->weekdays, true)) {
            return false;
        }

        $time = $moment->format('H:i');

        return $time >= $this->startTime->format('H:i') && $time < $this->endTime->format('H:i');
    }

    public function __toString(): string
    {
        return \sprintf('%s → %s', $this->origin, $this->destination);
    }
}

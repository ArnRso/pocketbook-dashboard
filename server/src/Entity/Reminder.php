<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReminderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Rappel affiché par la tuile « Rappels » pendant ses plages : « Sortir la poubelle verte ».
 */
#[ORM\Entity(repositoryClass: ReminderRepository::class)]
class Reminder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private string $text = '';

    #[ORM\Column]
    private bool $enabled = true;

    /** @var Collection<int, ReminderWindow> */
    #[ORM\OneToMany(targetEntity: ReminderWindow::class, mappedBy: 'reminder', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Assert\Count(min: 1, minMessage: 'Ajoute au moins une plage.')]
    #[Assert\Valid]
    private Collection $windows;

    public function __construct()
    {
        $this->windows = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

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

    /** @return Collection<int, ReminderWindow> */
    public function getWindows(): Collection
    {
        return $this->windows;
    }

    public function addWindow(ReminderWindow $window): static
    {
        if (!$this->windows->contains($window)) {
            $this->windows->add($window);
            $window->setReminder($this);
        }

        return $this;
    }

    public function removeWindow(ReminderWindow $window): static
    {
        $this->windows->removeElement($window);

        return $this;
    }

    /**
     * Plage en cours au moment donné, s'il y en a une.
     */
    public function windowAt(\DateTimeImmutable $moment): ?ReminderWindow
    {
        if (!$this->enabled) {
            return null;
        }

        return $this->windows->findFirst(static fn (int $key, ReminderWindow $window): bool => $window->contains($moment));
    }

    public function __toString(): string
    {
        return $this->text;
    }
}

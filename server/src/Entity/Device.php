<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DeviceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Liseuse autorisée à interroger l'API, authentifiée par une clé d'accès.
 */
#[ORM\Entity(repositoryClass: DeviceRepository::class)]
#[UniqueEntity('name', message: 'Une liseuse porte déjà ce nom.')]
class Device implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    private string $name = '';

    /** Empreinte SHA-256 de la clé : la clé en clair n'est jamais stockée */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $tokenHash = null;

    #[ORM\Column]
    #[Assert\Range(min: 60, max: 86400)]
    private int $refreshSeconds = 300;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getTokenHash(): ?string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(?string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function hasToken(): bool
    {
        return null !== $this->tokenHash;
    }

    public function getRefreshSeconds(): int
    {
        return $this->refreshSeconds;
    }

    public function setRefreshSeconds(int $refreshSeconds): static
    {
        $this->refreshSeconds = $refreshSeconds;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): static
    {
        $this->lastSeenAt = $lastSeenAt;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return 'device:'.$this->id;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_DEVICE'];
    }

    public function __toString(): string
    {
        return $this->name;
    }
}

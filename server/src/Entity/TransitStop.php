<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TransitStopRepository;
use App\Validator\ServedTransitStop;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Arrêt TBM suivi dans une ligne et une direction : « Palmer, tram E vers Gare de Blanquefort ».
 * Les noms sont relevés dans le réseau à l'enregistrement, pour l'affichage.
 */
#[ORM\Entity(repositoryClass: TransitStopRepository::class)]
#[ServedTransitStop]
class TransitStop implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Zone d'arrêt SIRI : bordeaux:StopPoint:BP:TPALMER:LOC */
    #[ORM\Column(length: 64)]
    #[Assert\NotBlank(message: 'Choisis un arrêt.')]
    private string $stopArea = '';

    #[ORM\Column(length: 100)]
    private string $stopName = '';

    /** Ligne SIRI : bordeaux:Line:163:LOC */
    #[ORM\Column(length: 64)]
    private string $line = '';

    #[ORM\Column(length: 16)]
    private string $lineCode = '';

    #[ORM\Column(length: 8)]
    private string $direction = '';

    #[ORM\Column(length: 100)]
    private string $destination = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStopArea(): string
    {
        return $this->stopArea;
    }

    public function setStopArea(?string $stopArea): static
    {
        $this->stopArea = $stopArea ?? '';

        return $this;
    }

    public function getLine(): string
    {
        return $this->line;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    /**
     * Ligne et direction ensemble, comme dans la liste de choix de l'admin : « bordeaux:Line:163:LOC#0 ».
     */
    #[Assert\NotBlank(message: 'Choisis une ligne et sa direction.')]
    public function getRoute(): ?string
    {
        return '' !== $this->line ? $this->line.'#'.$this->direction : null;
    }

    public function setRoute(?string $route): static
    {
        $route ??= '';
        $separator = strpos($route, '#');
        $this->line = false === $separator ? $route : substr($route, 0, $separator);
        $this->direction = false === $separator ? '' : substr($route, $separator + 1);

        return $this;
    }

    public function getStopName(): string
    {
        return $this->stopName;
    }

    public function getLineCode(): string
    {
        return $this->lineCode;
    }

    public function getDestination(): string
    {
        return $this->destination;
    }

    public function describe(string $stopName, string $lineCode, string $destination): static
    {
        $this->stopName = $stopName;
        $this->lineCode = $lineCode;
        $this->destination = $destination;

        return $this;
    }

    public function __toString(): string
    {
        return \sprintf('%s · %s → %s', $this->stopName, $this->lineCode, $this->destination);
    }
}

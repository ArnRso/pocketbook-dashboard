<?php

declare(strict_types=1);

namespace App\Entity\Tile;

use App\Entity\Dashboard;
use App\Entity\Schedule;
use App\Repository\TileRepository;
use App\Screen\Grid;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Emplacement d'un widget sur la grille d'un dashboard, avec ses réglages et ses horaires d'affichage.
 * Un nouveau type de widget = une sous-classe (ses réglages), déclarée dans la DiscriminatorMap.
 */
#[ORM\Entity(repositoryClass: TileRepository::class)]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'widget', type: 'string', length: 32)]
#[ORM\DiscriminatorMap([
    ClockTile::WIDGET => ClockTile::class,
    CurrentWeatherTile::WIDGET => CurrentWeatherTile::class,
    RainTile::WIDGET => RainTile::class,
    HourlyForecastTile::WIDGET => HourlyForecastTile::class,
    CommuteTile::WIDGET => CommuteTile::class,
])]
abstract class Tile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'tiles')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Dashboard $dashboard = null;

    #[ORM\Column(name: 'grid_column')]
    #[Assert\Range(min: 1, max: Grid::COLUMNS)]
    private int $column = 1;

    #[ORM\Column(name: 'grid_row')]
    #[Assert\Range(min: 1, max: Grid::ROWS)]
    private int $row = 1;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: Grid::COLUMNS)]
    private int $width;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: Grid::ROWS)]
    private int $height;

    #[ORM\Embedded(class: Schedule::class)]
    #[Assert\Valid]
    private Schedule $schedule;

    public function __construct()
    {
        $this->width = static::defaultWidth();
        $this->height = static::defaultHeight();
        $this->schedule = new Schedule();
    }

    /** Nom du widget : valeur de la colonne « widget » et nom du service qui l'affiche. */
    abstract public function widget(): string;

    /** Nom du type de tuile pour l'utilisateur. */
    abstract public static function label(): string;

    abstract protected static function defaultWidth(): int;

    abstract protected static function defaultHeight(): int;

    /** Description de la tuile dans l'éditeur, réglages compris. */
    public function describe(): string
    {
        return static::label();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDashboard(): ?Dashboard
    {
        return $this->dashboard;
    }

    public function setDashboard(?Dashboard $dashboard): static
    {
        $this->dashboard = $dashboard;

        return $this;
    }

    public function getColumn(): int
    {
        return $this->column;
    }

    public function getRow(): int
    {
        return $this->row;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function place(int $column, int $row, int $width, int $height): static
    {
        $this->column = $column;
        $this->row = $row;
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    public function getSchedule(): Schedule
    {
        return $this->schedule;
    }

    public function isVisibleAt(\DateTimeImmutable $moment): bool
    {
        return $this->schedule->isActiveAt($moment);
    }

    /**
     * Deux tuiles sont en conflit si elles se recouvrent sur la grille à un moment où elles sont toutes
     * deux affichées. Le trajet aller le matin et le retour le soir peuvent donc partager la même place.
     */
    public function conflictsWith(self $other): bool
    {
        return $this->column < $other->column + $other->width
            && $other->column < $this->column + $this->width
            && $this->row < $other->row + $other->height
            && $other->row < $this->row + $this->height
            && $this->schedule->intersects($other->schedule);
    }

    #[Assert\Callback]
    public function validateLayout(ExecutionContextInterface $context): void
    {
        if ($this->column + $this->width - 1 > Grid::COLUMNS) {
            $context->buildViolation(\sprintf('La tuile dépasse le bord droit de la grille (%d colonnes).', Grid::COLUMNS))
                ->atPath('width')
                ->addViolation();
        }
        if ($this->row + $this->height - 1 > Grid::ROWS) {
            $context->buildViolation(\sprintf('La tuile dépasse le bas de la grille (%d lignes).', Grid::ROWS))
                ->atPath('height')
                ->addViolation();
        }

        foreach ($this->dashboard?->getTiles() ?? [] as $other) {
            if ($other !== $this && $this->conflictsWith($other)) {
                $context->buildViolation(\sprintf('Cette tuile chevauche « %s » à des horaires communs.', $other->describe()))
                    ->addViolation();
            }
        }
    }
}

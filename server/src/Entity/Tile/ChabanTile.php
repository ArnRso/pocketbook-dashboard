<?php

declare(strict_types=1);

namespace App\Entity\Tile;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Prochaines fermetures du pont Jacques Chaban-Delmas, à Bordeaux.
 */
#[ORM\Entity]
class ChabanTile extends Tile
{
    public const WIDGET = 'chaban';
    public const DEFAULT_DAYS = 7;
    public const MAX_DAYS = 30;

    /** Nombre de jours à venir affichés */
    #[ORM\Column(nullable: true)]
    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: self::MAX_DAYS)]
    private ?int $days = self::DEFAULT_DAYS;

    public function widget(): string
    {
        return self::WIDGET;
    }

    public static function label(): string
    {
        return 'Pont Chaban-Delmas';
    }

    protected static function defaultWidth(): int
    {
        return 12;
    }

    protected static function defaultHeight(): int
    {
        return 3;
    }

    public static function maxHeight(): int
    {
        return 4;
    }

    public function getDays(): ?int
    {
        return $this->days;
    }

    public function setDays(?int $days): static
    {
        $this->days = $days;

        return $this;
    }

    public function describe(): string
    {
        return \sprintf('%s · %d jours', static::label(), $this->days ?? self::DEFAULT_DAYS);
    }
}

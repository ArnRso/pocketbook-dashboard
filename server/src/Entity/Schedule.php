<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Jours et plage horaire pendant lesquels une tuile est affichée. Sans plage horaire : toute la journée.
 */
#[ORM\Embeddable]
class Schedule
{
    /** @var list<int> Jours ISO-8601 : 1 = lundi … 7 = dimanche */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Count(min: 1, minMessage: 'Choisis au moins un jour.')]
    private array $weekdays = [1, 2, 3, 4, 5, 6, 7];

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startTime = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endTime = null;

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

    #[Assert\Callback]
    public function validateTimeRange(ExecutionContextInterface $context): void
    {
        if ((null === $this->startTime) !== (null === $this->endTime)) {
            $context->buildViolation('Renseigne l\'heure de début et l\'heure de fin, ou aucune des deux.')
                ->atPath(null === $this->startTime ? 'startTime' : 'endTime')
                ->addViolation();
        } elseif (null !== $this->startTime && null !== $this->endTime && $this->endTime <= $this->startTime) {
            $context->buildViolation('L\'heure de fin doit être après l\'heure de début.')
                ->atPath('endTime')
                ->addViolation();
        }
    }

    public function isActiveAt(\DateTimeImmutable $moment): bool
    {
        if (!\in_array((int) $moment->format('N'), $this->weekdays, true)) {
            return false;
        }

        $minute = self::minuteOfDay($moment);

        return $minute >= $this->startMinute() && $minute < $this->endMinute();
    }

    /**
     * Vrai si les deux horaires ont au moins un instant en commun.
     */
    public function intersects(self $other): bool
    {
        if ([] === array_intersect($this->weekdays, $other->weekdays)) {
            return false;
        }

        return $this->startMinute() < $other->endMinute() && $other->startMinute() < $this->endMinute();
    }

    /** Début de la plage en minutes depuis minuit ; minuit sans plage horaire. */
    private function startMinute(): int
    {
        return null !== $this->startTime && null !== $this->endTime ? self::minuteOfDay($this->startTime) : 0;
    }

    /** Fin (exclue) de la plage en minutes depuis minuit ; minuit le lendemain sans plage horaire. */
    private function endMinute(): int
    {
        return null !== $this->startTime && null !== $this->endTime ? self::minuteOfDay($this->endTime) : 24 * 60;
    }

    private static function minuteOfDay(\DateTimeImmutable $time): int
    {
        return (int) $time->format('G') * 60 + (int) $time->format('i');
    }

    public function __toString(): string
    {
        $days = 7 === \count($this->weekdays)
            ? 'Tous les jours'
            : implode(', ', array_map(static fn (int $day): string => mb_substr(Weekday::from($day)->label(), 0, 3), $this->weekdays));

        if (null === $this->startTime || null === $this->endTime) {
            return $days;
        }

        return \sprintf('%s, %s–%s', $days, $this->startTime->format('H:i'), $this->endTime->format('H:i'));
    }
}

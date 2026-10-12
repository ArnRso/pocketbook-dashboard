<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Plage hebdomadaire d'un rappel, d'un jour et d'une heure à d'autres : « dimanche 16:00 → lundi 09:00 ».
 * Une plage qui finit avant de commencer dans la semaine (samedi → mardi) passe sur la semaine suivante.
 */
#[ORM\Entity]
class ReminderWindow
{
    private const MINUTES_PER_DAY = 24 * 60;
    private const MINUTES_PER_WEEK = 7 * self::MINUTES_PER_DAY;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'windows')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Reminder $reminder = null;

    #[ORM\Column(enumType: Weekday::class)]
    #[Assert\NotNull]
    private ?Weekday $startDay = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startTime = null;

    #[ORM\Column(enumType: Weekday::class)]
    #[Assert\NotNull]
    private ?Weekday $endDay = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $endTime = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReminder(): ?Reminder
    {
        return $this->reminder;
    }

    public function setReminder(?Reminder $reminder): static
    {
        $this->reminder = $reminder;

        return $this;
    }

    public function getStartDay(): ?Weekday
    {
        return $this->startDay;
    }

    public function setStartDay(?Weekday $startDay): static
    {
        $this->startDay = $startDay;

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

    public function getEndDay(): ?Weekday
    {
        return $this->endDay;
    }

    public function setEndDay(?Weekday $endDay): static
    {
        $this->endDay = $endDay;

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
    public function validateRange(ExecutionContextInterface $context): void
    {
        if (null !== $this->start() && $this->start() === $this->end()) {
            $context->buildViolation('La fin de la plage doit être différente de son début.')
                ->atPath('endTime')
                ->addViolation();
        }
    }

    public function contains(\DateTimeImmutable $moment): bool
    {
        $start = $this->start();
        $end = $this->end();
        if (null === $start || null === $end) {
            return false;
        }

        $minute = self::minuteOfWeek(Weekday::from((int) $moment->format('N')), $moment);

        return $start < $end
            ? $minute >= $start && $minute < $end
            : $minute >= $start || $minute < $end;
    }

    /**
     * Prochaine fin de la plage après le moment donné, à l'heure locale (même un jour de changement d'heure).
     */
    public function endingAfter(\DateTimeImmutable $moment): \DateTimeImmutable
    {
        $end = $this->end() ?? throw new \LogicException('Plage incomplète.');
        $minute = self::minuteOfWeek(Weekday::from((int) $moment->format('N')), $moment);
        $remaining = ($end - $minute + self::MINUTES_PER_WEEK - 1) % self::MINUTES_PER_WEEK + 1;
        $days = intdiv($minute % self::MINUTES_PER_DAY + $remaining, self::MINUTES_PER_DAY);

        return $moment->modify(\sprintf('+%d days', $days))->setTime(intdiv($end % self::MINUTES_PER_DAY, 60), $end % 60);
    }

    private function start(): ?int
    {
        return null !== $this->startDay && null !== $this->startTime ? self::minuteOfWeek($this->startDay, $this->startTime) : null;
    }

    private function end(): ?int
    {
        return null !== $this->endDay && null !== $this->endTime ? self::minuteOfWeek($this->endDay, $this->endTime) : null;
    }

    /** Minutes écoulées depuis lundi 00:00. */
    private static function minuteOfWeek(Weekday $day, \DateTimeImmutable $time): int
    {
        return ($day->value - 1) * self::MINUTES_PER_DAY + (int) $time->format('G') * 60 + (int) $time->format('i');
    }

    public function __toString(): string
    {
        return \sprintf(
            '%s %s → %s %s',
            mb_substr($this->startDay?->label() ?? '?', 0, 3),
            $this->startTime?->format('H:i') ?? '?',
            mb_substr($this->endDay?->label() ?? '?', 0, 3),
            $this->endTime?->format('H:i') ?? '?',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Widget;

use App\Entity\Tile\ReminderTile;
use App\Entity\Tile\Tile;
use App\Repository\ReminderRepository;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(ReminderTile::WIDGET)]
final class ReminderWidget implements WidgetInterface
{
    public function __construct(
        private readonly ReminderRepository $reminders,
    ) {
    }

    public function template(): string
    {
        return 'widget/reminders.html.twig';
    }

    public function data(Tile $tile, \DateTimeImmutable $at): ReminderView
    {
        if (!$tile instanceof ReminderTile) {
            throw UnsupportedTileException::for($tile, ReminderTile::class);
        }

        $active = [];
        foreach ($this->reminders->findEnabled() as $reminder) {
            $window = $reminder->windowAt($at);
            if (null !== $window) {
                $active[] = new ActiveReminder($reminder->getText(), $window->endingAfter($at));
            }
        }

        return [] !== $active ? new ReminderView($active) : throw new NothingToShowException('Aucun rappel en cours.');
    }
}

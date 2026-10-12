<?php

declare(strict_types=1);

namespace App\Widget;

final readonly class ReminderView
{
    /**
     * @param non-empty-list<ActiveReminder> $reminders
     */
    public function __construct(
        public array $reminders,
    ) {
    }
}

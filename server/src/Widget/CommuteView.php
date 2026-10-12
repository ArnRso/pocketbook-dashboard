<?php

declare(strict_types=1);

namespace App\Widget;

use App\Traffic\Commute;

final readonly class CommuteView
{
    /**
     * @param Commute|null $commute null quand aucun trajet n'est prévu à cette heure
     */
    public function __construct(
        public ?Commute $commute,
    ) {
    }
}

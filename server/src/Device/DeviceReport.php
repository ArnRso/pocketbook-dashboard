<?php

declare(strict_types=1);

namespace App\Device;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * État transmis par la liseuse avec sa demande d'écran : /api/v1/screen?battery=87&charging=1.
 */
final class DeviceReport
{
    /** Niveau de batterie en % ; absent pour les versions de l'application qui ne le transmettent pas */
    #[Assert\Range(min: 0, max: 100)]
    public ?int $battery = null;

    public bool $charging = false;
}

<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * L'arrêt TBM existe et la ligne choisie le dessert dans la direction choisie.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ServedTransitStop extends Constraint
{
    public string $notServedMessage = 'La ligne {{ line }} ne dessert pas cet arrêt.';
    public string $unknownMessage = 'Arrêt ou ligne inconnu du réseau TBM.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}

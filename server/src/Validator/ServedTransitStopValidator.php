<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\TransitStop;
use App\Transit\TransitException;
use App\Transit\TransitNetwork;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ServedTransitStopValidator extends ConstraintValidator
{
    public function __construct(
        private readonly TransitNetwork $network,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ServedTransitStop) {
            throw new UnexpectedTypeException($constraint, ServedTransitStop::class);
        }
        if (!$value instanceof TransitStop) {
            throw new UnexpectedValueException($value, TransitStop::class);
        }
        // Champs vides : signalés par leurs propres contraintes
        if ('' === $value->getStopArea() || '' === $value->getLine()) {
            return;
        }

        try {
            $area = $this->network->stopArea($value->getStopArea());
            $line = $this->network->line($value->getLine());
        } catch (TransitException $e) {
            $this->context->buildViolation($e->getMessage())->addViolation();

            return;
        }

        if (null === $area || null === $line || !isset($line->destinations[$value->getDirection()])) {
            $this->context->buildViolation($constraint->unknownMessage)->addViolation();
        } elseif (!$area->isServedBy($line->ref)) {
            $this->context->buildViolation($constraint->notServedMessage)
                ->setParameter('{{ line }}', $line->name)
                ->atPath('route')
                ->addViolation();
        }
    }
}

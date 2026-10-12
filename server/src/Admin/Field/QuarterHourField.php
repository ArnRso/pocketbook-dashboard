<?php

declare(strict_types=1);

namespace App\Admin\Field;

use App\Form\QuarterHourType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Heure affichée « HH:MM » dans les listes et choisie au quart d'heure dans les formulaires.
 */
final class QuarterHourField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplatePath('admin/field/quarter_hour.html.twig')
            ->setFormType(QuarterHourType::class)
            ->setFormTypeOption('input', 'datetime_immutable')
            ->formatValue(static fn (?\DateTimeImmutable $time): ?string => $time?->format('H:i'));
    }
}

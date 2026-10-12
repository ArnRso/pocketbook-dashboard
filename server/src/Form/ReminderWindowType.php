<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ReminderWindow;
use App\Entity\Weekday;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Plage d'un rappel : du jour et de l'heure de début au jour et à l'heure de fin.
 *
 * @extends AbstractType<ReminderWindow>
 */
final class ReminderWindowType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDay', EnumType::class, [
                'label' => 'Du',
                'class' => Weekday::class,
                'choice_label' => static fn (Weekday $day): string => $day->label(),
            ])
            ->add('startTime', QuarterHourType::class, [
                'label' => 'à',
                'input' => 'datetime_immutable',
            ])
            ->add('endDay', EnumType::class, [
                'label' => 'Au',
                'class' => Weekday::class,
                'choice_label' => static fn (Weekday $day): string => $day->label(),
            ])
            ->add('endTime', QuarterHourType::class, [
                'label' => 'à',
                'input' => 'datetime_immutable',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ReminderWindow::class]);
    }
}

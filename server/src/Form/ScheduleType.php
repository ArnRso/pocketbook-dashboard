<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Schedule;
use App\Entity\Weekday;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Jours et plage horaire d'affichage d'une tuile.
 *
 * @extends AbstractType<Schedule>
 */
final class ScheduleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('weekdays', ChoiceType::class, [
                'label' => 'Jours',
                'choices' => Weekday::choices(),
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('startTime', QuarterHourType::class, [
                'label' => 'De',
                'input' => 'datetime_immutable',
                'placeholder' => '—',
                'required' => false,
            ])
            ->add('endTime', QuarterHourType::class, [
                'label' => 'À',
                'input' => 'datetime_immutable',
                'placeholder' => '—',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Schedule::class]);
    }
}

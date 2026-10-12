<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Weekday;
use App\Screen\PreviewQuery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Choix du jour et de l'heure simulés dans l'aperçu de l'admin, pour vérifier quels widgets s'affichent.
 *
 * @extends AbstractType<PreviewQuery>
 */
final class PreviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('day', ChoiceType::class, [
                'label' => 'Jour',
                'choices' => Weekday::choices(),
                'placeholder' => 'Aujourd\'hui',
                'required' => false,
            ])
            ->add('time', QuarterHourType::class, [
                'label' => 'Heure',
                'placeholder' => 'Maintenant',
                'required' => false,
            ])
            ->add('preview', SubmitType::class, [
                'label' => 'Prévisualiser',
                'attr' => ['class' => 'btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PreviewQuery::class,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}

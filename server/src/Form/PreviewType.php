<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Choix du moment simulé dans l'aperçu de l'admin, pour vérifier quels widgets s'affichent.
 *
 * @extends AbstractType<array{at: ?\DateTimeImmutable}>
 */
final class PreviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('at', DateTimeType::class, [
                'label' => 'Moment simulé',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'help' => 'Laisser vide pour l\'heure actuelle. Météo et trafic restent ceux du moment présent.',
            ])
            ->add('preview', SubmitType::class, [
                'label' => 'Prévisualiser',
                'attr' => ['class' => 'btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}

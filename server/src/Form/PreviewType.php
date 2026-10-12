<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Dashboard;
use App\Entity\Weekday;
use App\Repository\DashboardRepository;
use App\Screen\PreviewQuery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Choix du dashboard, du jour et de l'heure simulés dans l'aperçu de l'admin, pour vérifier quelles tuiles s'affichent.
 *
 * @extends AbstractType<PreviewQuery>
 */
final class PreviewType extends AbstractType
{
    public function __construct(
        private readonly DashboardRepository $dashboards,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dashboard', ChoiceType::class, [
                'label' => 'Dashboard',
                'choices' => $this->dashboards->findBy([], ['name' => 'ASC']),
                'choice_value' => static fn (Dashboard|int|null $dashboard): ?int => $dashboard instanceof Dashboard ? $dashboard->getId() : $dashboard,
                'choice_label' => static fn (Dashboard $dashboard): string => $dashboard->getName(),
                'getter' => static fn (PreviewQuery $query): ?int => $query->dashboard,
                'setter' => static function (PreviewQuery $query, ?Dashboard $dashboard): void {
                    $query->dashboard = $dashboard?->getId();
                },
            ])
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

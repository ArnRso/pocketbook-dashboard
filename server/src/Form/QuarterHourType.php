<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Liste déroulante des heures au quart d'heure (00:00, 00:15… 23:45), plus simple sur tous les
 * navigateurs que le sélecteur d'heure natif. input : « string » (HH:MM) ou « datetime_immutable ».
 *
 * @extends AbstractType<string|\DateTimeImmutable>
 */
final class QuarterHourType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ('datetime_immutable' === ($options['input'] ?? null)) {
            $builder->addModelTransformer(new QuarterHourTransformer());
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => self::choices(),
            'choice_translation_domain' => false,
            'input' => 'string',
        ]);
        $resolver->setAllowedValues('input', ['string', 'datetime_immutable']);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    /**
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (range(0, 23) as $hour) {
            foreach ([0, 15, 30, 45] as $minute) {
                $time = \sprintf('%02d:%02d', $hour, $minute);
                $choices[$time] = $time;
            }
        }

        return $choices;
    }
}

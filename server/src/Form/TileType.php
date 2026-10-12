<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Place;
use App\Entity\Tile\ChabanTile;
use App\Entity\Tile\CommuteTile;
use App\Entity\Tile\Tile;
use App\Entity\Tile\TileSize;
use App\Entity\Tile\TileWidth;
use App\Entity\Tile\WeatherTile;
use App\Entity\Trip;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Réglages d'une tuile : ceux de son type (lieu, trajet…), sa taille en mise en page automatique et
 * ses horaires d'affichage.
 *
 * @extends AbstractType<Tile>
 */
final class TileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $tile = $options['data'] ?? null;

        if ($tile instanceof WeatherTile) {
            $builder->add('place', EntityType::class, [
                'label' => 'Lieu',
                'class' => Place::class,
                'choice_label' => 'label',
                'placeholder' => 'Choisir un lieu',
            ]);
        }
        if ($tile instanceof CommuteTile) {
            $builder->add('trip', EntityType::class, [
                'label' => 'Trajet',
                'class' => Trip::class,
                'placeholder' => 'Choisir un trajet',
            ]);
        }
        if ($tile instanceof ChabanTile) {
            $builder->add('days', IntegerType::class, [
                'label' => 'Nombre de jours à venir',
                'attr' => ['min' => 1, 'max' => ChabanTile::MAX_DAYS],
            ]);
        }
        if (true === ($options['auto_layout'] ?? false)) {
            $builder
                ->add('autoWidth', EnumType::class, [
                    'label' => 'Largeur',
                    'class' => TileWidth::class,
                    'choice_label' => static fn (TileWidth $width): string => $width->label(),
                ])
                ->add('autoSize', EnumType::class, [
                    'label' => 'Hauteur',
                    'class' => TileSize::class,
                    'choice_label' => static fn (TileSize $size): string => $size->label(),
                ]);
        }

        $builder
            ->add('schedule', ScheduleType::class, ['label' => 'Affichage'])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tile::class,
            // Largeur et hauteur de la tuile en mise en page automatique
            'auto_layout' => false,
        ]);
        $resolver->setAllowedTypes('auto_layout', 'bool');
    }
}

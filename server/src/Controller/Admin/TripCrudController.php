<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Trip;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;

/**
 * @extends AbstractCrudController<Trip>
 */
class TripCrudController extends AbstractCrudController
{
    private const WEEKDAYS = [
        'Lundi' => 1,
        'Mardi' => 2,
        'Mercredi' => 3,
        'Jeudi' => 4,
        'Vendredi' => 5,
        'Samedi' => 6,
        'Dimanche' => 7,
    ];

    public static function getEntityFqcn(): string
    {
        return Trip::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Trajet')
            ->setEntityLabelInPlural('Trajets')
            ->setPageTitle(Crud::PAGE_NEW, 'Ajouter un trajet')
            ->setHelp(Crud::PAGE_INDEX, 'Le dashboard affiche le premier trajet actif dont la plage horaire contient l\'heure courante.')
            ->setDefaultSort(['startTime' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('origin', 'Départ');
        yield AssociationField::new('destination', 'Arrivée');
        yield TimeField::new('startTime', 'Affiché de')
            ->setFormTypeOption('input', 'datetime_immutable')
            ->setFormat('HH:mm');
        yield TimeField::new('endTime', 'à')
            ->setFormTypeOption('input', 'datetime_immutable')
            ->setFormat('HH:mm');
        yield ChoiceField::new('weekdays', 'Jours')
            ->setChoices(self::WEEKDAYS)
            ->allowMultipleChoices()
            ->renderExpanded();
        yield BooleanField::new('avoidTolls', 'Éviter les péages');
        yield BooleanField::new('enabled', 'Actif');
    }
}

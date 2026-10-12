<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Field\QuarterHourField;
use App\Entity\Trip;
use App\Entity\Weekday;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

/**
 * @extends AbstractCrudController<Trip>
 */
class TripCrudController extends AbstractCrudController
{
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
        yield QuarterHourField::new('startTime', 'Affiché de');
        yield QuarterHourField::new('endTime', 'à');
        yield ChoiceField::new('weekdays', 'Jours')
            ->setChoices(Weekday::choices())
            ->allowMultipleChoices()
            ->renderExpanded();
        yield BooleanField::new('avoidTolls', 'Éviter les péages');
        yield BooleanField::new('enabled', 'Actif');
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Trip;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;

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
            ->setHelp(Crud::PAGE_INDEX, 'Un trajet s\'affiche dans une tuile « Trajet » d\'un dashboard, aux horaires de la tuile.');
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('origin', 'Départ');
        yield AssociationField::new('destination', 'Arrivée');
        yield BooleanField::new('avoidTolls', 'Éviter les péages');
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Place;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Place>
 */
class PlaceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Place::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Lieu')
            ->setEntityLabelInPlural('Lieux')
            ->setPageTitle(Crud::PAGE_NEW, 'Ajouter un lieu');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('label', 'Nom');
        yield TextField::new('address', 'Adresse');
        yield NumberField::new('latitude')
            ->setNumDecimals(6)
            ->setHelp('Laisser vide pour la calculer à partir de l\'adresse.');
        yield NumberField::new('longitude')
            ->setNumDecimals(6)
            ->setHelp('Laisser vide pour la calculer à partir de l\'adresse.');
    }
}

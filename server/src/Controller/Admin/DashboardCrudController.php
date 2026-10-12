<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Dashboard>
 */
class DashboardCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Dashboard::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Dashboard')
            ->setEntityLabelInPlural('Dashboards')
            ->setPageTitle(Crud::PAGE_NEW, 'Ajouter un dashboard')
            ->setHelp(Crud::PAGE_INDEX, 'Un dashboard est un écran composé de tuiles ; chaque liseuse affiche celui qui lui est assigné.')
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield IntegerField::new('tiles.count', 'Tuiles')
            ->hideOnForm();
    }
}

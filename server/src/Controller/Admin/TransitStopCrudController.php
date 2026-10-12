<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TransitStop;
use App\Transit\TransitException;
use App\Transit\TransitNetwork;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<TransitStop>
 */
class TransitStopCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TransitNetwork $network,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return TransitStop::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Arrêt TBM')
            ->setEntityLabelInPlural('Arrêts TBM')
            ->setPageTitle(Crud::PAGE_NEW, 'Ajouter un arrêt TBM')
            ->setHelp(Crud::PAGE_INDEX, 'Un arrêt suivi dans une ligne et une direction ; ses prochains passages s\'affichent dans une tuile « Prochains passages TBM ».');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('stopName', 'Arrêt')->hideOnForm();
        yield TextField::new('lineCode', 'Ligne')->hideOnForm();
        yield TextField::new('destination', 'Direction')->hideOnForm();
        if (!\in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT], true)) {
            return;
        }

        try {
            $stops = $this->network->stopChoices();
            $routes = $this->network->routeChoices();
        } catch (TransitException $e) {
            $this->addFlash('danger', $e->getMessage());
            $stops = $routes = [];
        }
        yield ChoiceField::new('stopArea', 'Arrêt')
            ->setChoices($stops)
            ->autocomplete()
            ->setHelp('Entre parenthèses, les lignes qui desservent l\'arrêt.');
        yield ChoiceField::new('route', 'Ligne et direction')
            ->setChoices($routes)
            ->autocomplete();
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TransitStop;
use App\Transit\RouteChoice;
use App\Transit\TransitException;
use App\Transit\TransitNetwork;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

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
        // Toutes les directions restent valides côté serveur ; la liste n'affiche que celles de l'arrêt choisi
        yield ChoiceField::new('route', 'Ligne et direction')
            ->setChoices($routes)
            ->autocomplete()
            ->setFormTypeOption('attr', ['data-routes-url' => $this->generateUrl('admin_transit_stop_routes')]);
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addAssetMapperEntry('transit-stop-form');
    }

    /**
     * Lignes et directions qui desservent l'arrêt, pour la liste du formulaire.
     */
    #[AdminRoute('/routes', name: 'routes', options: ['methods' => ['GET']])]
    public function routes(#[MapQueryParameter] string $stop): JsonResponse
    {
        try {
            $routes = $this->network->routeChoices($stop);
        } catch (TransitException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json(array_map(static fn (string $label, string $route): RouteChoice => new RouteChoice($route, $label), array_keys($routes), array_values($routes)));
    }
}

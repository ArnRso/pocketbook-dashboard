<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Dashboard;
use App\Entity\DashboardLayout;
use App\Entity\Tile\Tile;
use App\Form\PreviewType;
use App\Form\TileType;
use App\Screen\Grid;
use App\Screen\PreviewMoment;
use App\Screen\PreviewQuery;
use App\Screen\TileEditor;
use App\Screen\TileLayout;
use App\Screen\TileLayoutException;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

/**
 * @extends AbstractCrudController<Dashboard>
 */
class DashboardCrudController extends AbstractCrudController
{
    /** La tuile de l'URL n'est trouvée que si elle appartient au dashboard de l'URL : 404 sinon */
    private const TILE = ['tile' => 'id', 'id' => 'dashboard'];

    public function __construct(
        private readonly TileEditor $tiles,
        private readonly PreviewMoment $previewMoment,
        private readonly ClockInterface $clock,
    ) {
    }

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

    public function configureActions(Actions $actions): Actions
    {
        $grid = Action::new('grid', 'Éditer les tuiles', 'fa fa-table-cells')
            ->linkToCrudAction('grid');

        return $actions
            ->add(Crud::PAGE_INDEX, $grid)
            ->add(Crud::PAGE_DETAIL, $grid);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield ChoiceField::new('layout', 'Mise en page')
            ->renderExpanded()
            ->setHelp('Automatique : les tuiles affichées s\'empilent dans leur ordre et occupent tout l\'écran. Grille libre : chaque tuile a sa place fixe.');
        yield IntegerField::new('tiles.count', 'Tuiles')
            ->hideOnForm();
    }

    /**
     * Éditeur de grille : les tuiles visibles au moment choisi se déplacent et se redimensionnent à la souris.
     */
    #[AdminRoute('/{id}/grid', name: 'grid', options: ['methods' => ['GET']])]
    public function grid(Dashboard $dashboard, Request $request): Response
    {
        $query = new PreviewQuery();
        $form = $this->createForm(PreviewType::class, $query, ['with_dashboard' => false]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && !$form->isValid()) {
            $query = new PreviewQuery();
        }
        $query->dashboard = $dashboard->getId();
        $at = $this->previewMoment->resolve($query) ?? $this->clock->now();

        return $this->render('admin/dashboard_grid.html.twig', [
            'dashboard' => $dashboard,
            'auto' => DashboardLayout::Auto === $dashboard->getLayout(),
            'form' => $form,
            'query' => $query,
            'at' => $at,
            'tiles' => $dashboard->orderedTiles(),
            'visible' => $dashboard->tilesVisibleAt($at),
            'hidden' => $dashboard->tilesHiddenAt($at),
            'types' => $this->tiles->types(),
            'columns' => Grid::COLUMNS,
            'rows' => Grid::ROWS,
        ]);
    }

    #[AdminRoute('/{id}/tiles/{tile}/layout', name: 'tile_layout', options: ['methods' => ['POST']])]
    public function tileLayout(
        Dashboard $dashboard,
        #[MapEntity(mapping: self::TILE)] Tile $tile,
        #[MapRequestPayload] TileLayout $layout,
        Request $request,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('tile-layout', (string) $request->headers->get('X-CSRF-Token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $this->tiles->move($tile, $layout);
        } catch (TileLayoutException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Monte ou descend la tuile d'un rang dans la mise en page automatique.
     */
    #[AdminRoute('/{id}/tiles/{tile}/shift/{direction}', name: 'tile_shift', options: ['methods' => ['POST'], 'requirements' => ['direction' => 'up|down']])]
    public function shiftTile(Dashboard $dashboard, #[MapEntity(mapping: self::TILE)] Tile $tile, string $direction, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('tile-shift-'.$tile->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->tiles->shift($tile, 'up' === $direction ? -1 : 1);

        return $this->redirectToRoute('admin_dashboard_grid', ['id' => $dashboard->getId(), ...$request->query->all()]);
    }

    #[AdminRoute('/{id}/tiles/new/{widget}', name: 'tile_new', options: ['methods' => ['GET', 'POST'], 'requirements' => ['widget' => '[a-z_]+']])]
    public function newTile(Dashboard $dashboard, string $widget, Request $request): Response
    {
        try {
            $tile = $this->tiles->create($widget);
        } catch (\InvalidArgumentException $e) {
            throw $this->createNotFoundException($e->getMessage(), $e);
        }

        $form = $this->createForm(TileType::class, $tile, ['auto_layout' => DashboardLayout::Auto === $dashboard->getLayout()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->tiles->add($dashboard, $tile);

                return $this->backToGrid($dashboard, \sprintf('Tuile « %s » ajoutée.', $tile->describe()));
            } catch (TileLayoutException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('admin/tile_form.html.twig', [
            'dashboard' => $dashboard,
            'title' => \sprintf('Ajouter une tuile « %s »', $tile::label()),
            'form' => $form,
        ]);
    }

    #[AdminRoute('/{id}/tiles/{tile}/edit', name: 'tile_edit', options: ['methods' => ['GET', 'POST']])]
    public function editTile(Dashboard $dashboard, #[MapEntity(mapping: self::TILE)] Tile $tile, Request $request): Response
    {
        $form = $this->createForm(TileType::class, $tile, ['auto_layout' => DashboardLayout::Auto === $dashboard->getLayout()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->tiles->save($tile);

            return $this->backToGrid($dashboard, \sprintf('Tuile « %s » enregistrée.', $tile->describe()));
        }

        return $this->render('admin/tile_form.html.twig', [
            'dashboard' => $dashboard,
            'title' => \sprintf('Modifier « %s »', $tile->describe()),
            'form' => $form,
        ]);
    }

    #[AdminRoute('/{id}/tiles/{tile}/delete', name: 'tile_delete', options: ['methods' => ['POST']])]
    public function deleteTile(Dashboard $dashboard, #[MapEntity(mapping: self::TILE)] Tile $tile, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('tile-delete-'.$tile->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $description = $tile->describe();
        $this->tiles->remove($tile);

        return $this->backToGrid($dashboard, \sprintf('Tuile « %s » supprimée.', $description));
    }

    private function backToGrid(Dashboard $dashboard, string $message): RedirectResponse
    {
        $this->addFlash('success', $message);

        return $this->redirectToRoute('admin_dashboard_grid', ['id' => $dashboard->getId()]);
    }
}

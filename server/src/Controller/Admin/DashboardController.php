<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Form\PreviewType;
use App\Repository\DashboardRepository;
use App\Screen\PreviewMoment;
use App\Screen\PreviewQuery;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly PreviewMoment $previewMoment,
        private readonly DashboardRepository $dashboards,
    ) {
    }

    public function index(): Response
    {
        $query = new PreviewQuery();
        $form = $this->createForm(PreviewType::class, $query);
        $form->handleRequest($this->requestStack->getCurrentRequest());
        if ($form->isSubmitted() && !$form->isValid()) {
            $query = new PreviewQuery();
        }

        $dashboard = $this->dashboards->findForPreview($query->dashboard);

        return $this->render('admin/index.html.twig', [
            'form' => $form,
            'query' => $query,
            'dashboard' => $dashboard,
            'at' => $this->previewMoment->resolve($query),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('PocketBook Dashboard')
            ->setLocales(['fr']);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Aperçu', 'fa fa-image');
        yield MenuItem::section('Configuration');
        yield MenuItem::linkTo(DashboardCrudController::class, 'Dashboards', 'fa fa-table-cells');
        yield MenuItem::linkTo(PlaceCrudController::class, 'Lieux', 'fa fa-location-dot');
        yield MenuItem::linkTo(TripCrudController::class, 'Trajets', 'fa fa-car');
        yield MenuItem::linkTo(ReminderCrudController::class, 'Rappels', 'fa fa-bell');
        yield MenuItem::linkTo(DeviceCrudController::class, 'Liseuses', 'fa fa-tablet-screen-button');
        yield MenuItem::section();
        yield MenuItem::linkToLogout('Déconnexion', 'fa fa-right-from-bracket');
    }
}

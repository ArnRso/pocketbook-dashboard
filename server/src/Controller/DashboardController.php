<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\DashboardRepository;
use App\Screen\PreviewMoment;
use App\Screen\PreviewQuery;
use App\Screen\ScreenRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): RedirectResponse
    {
        return $this->redirectToRoute('admin');
    }

    /**
     * Version HTML de l'écran, pratique pour travailler la mise en page dans un navigateur.
     */
    #[Route('/preview', name: 'app_preview', methods: ['GET'])]
    public function preview(
        ScreenRenderer $screen,
        PreviewMoment $moment,
        DashboardRepository $dashboards,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        PreviewQuery $query = new PreviewQuery(),
    ): Response {
        return new Response($screen->html($dashboards->findForPreview($query->dashboard), $moment->resolve($query)));
    }

    /**
     * Image identique à celle reçue par les liseuses.
     */
    #[Route('/preview.png', name: 'app_preview_image', methods: ['GET'])]
    public function previewImage(
        ScreenRenderer $screen,
        PreviewMoment $moment,
        DashboardRepository $dashboards,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        PreviewQuery $query = new PreviewQuery(),
    ): Response {
        return new Response($screen->png($dashboards->findForPreview($query->dashboard), $moment->resolve($query)), Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store',
        ]);
    }
}

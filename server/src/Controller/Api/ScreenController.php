<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Device;
use App\Screen\ScreenRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ScreenController extends AbstractController
{
    /**
     * Image à afficher. L'en-tête X-Refresh-Interval indique à la liseuse dans combien
     * de secondes revenir chercher l'écran suivant.
     */
    #[Route('/api/v1/screen', name: 'api_screen', methods: ['GET'])]
    public function screen(#[CurrentUser] Device $device, ScreenRenderer $screen): Response
    {
        return new Response($screen->png(), Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store',
            'X-Refresh-Interval' => $device->getRefreshSeconds(),
        ]);
    }
}

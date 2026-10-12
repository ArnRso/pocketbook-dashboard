<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Device\DeviceManager;
use App\Device\DeviceReport;
use App\Entity\Device;
use App\Screen\ScreenRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ScreenController extends AbstractController
{
    /**
     * Image à afficher. La liseuse y joint son niveau de batterie ; l'en-tête X-Refresh-Interval lui
     * indique dans combien de secondes revenir chercher l'écran suivant.
     */
    #[Route('/api/v1/screen', name: 'api_screen', methods: ['GET'])]
    public function screen(
        #[CurrentUser] Device $device,
        ScreenRenderer $screen,
        DeviceManager $devices,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        DeviceReport $report = new DeviceReport(),
    ): Response {
        $devices->report($device, $report);

        return new Response($screen->png($device->getDashboard(), device: $device), Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store',
            'X-Refresh-Interval' => $device->getRefreshSeconds(),
        ]);
    }
}

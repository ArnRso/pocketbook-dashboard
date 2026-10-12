<?php

declare(strict_types=1);

namespace App\Widget;

use App\Entity\Device;
use App\Entity\Tile\Tile;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Twig\Environment;

/**
 * Affiche un widget ; s'il est indisponible, affiche la raison à sa place sans bloquer le reste de l'écran.
 */
class WidgetRenderer
{
    /**
     * @param ServiceLocator<WidgetInterface> $widgets
     */
    public function __construct(
        #[AutowireLocator(WidgetInterface::TAG)]
        private readonly ServiceLocator $widgets,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return string|null HTML du widget, null s'il n'a rien à afficher
     */
    public function render(Tile $tile, \DateTimeImmutable $at, ?Device $device = null): ?string
    {
        $widget = $this->widgets->get($tile->widget());

        try {
            return $this->twig->render($widget->template(), ['data' => $widget->data($tile, $at, $device), 'tile' => $tile]);
        } catch (NothingToShowException) {
            return null;
        } catch (DisplayableException $e) {
            $this->logger->warning('Tuile « {tile} » indisponible : {message}', ['tile' => $tile->describe(), 'message' => $e->getMessage(), 'exception' => $e]);

            return $this->twig->render('widget/_unavailable.html.twig', ['tile' => $tile, 'message' => $e->getMessage()]);
        }
    }
}

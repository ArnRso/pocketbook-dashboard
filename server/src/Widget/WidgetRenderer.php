<?php

declare(strict_types=1);

namespace App\Widget;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Twig\Attribute\AsTwigFunction;
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

    #[AsTwigFunction('widget', isSafe: ['html'])]
    public function render(string $name, \DateTimeImmutable $at): string
    {
        $widget = $this->widgets->get($name);

        try {
            return $this->twig->render($widget->template(), ['data' => $widget->data($at)]);
        } catch (DisplayableException $e) {
            $this->logger->warning('Widget "{widget}" indisponible : {message}', ['widget' => $name, 'message' => $e->getMessage(), 'exception' => $e]);

            return $this->twig->render('widget/_unavailable.html.twig', ['name' => $name, 'message' => $e->getMessage()]);
        }
    }
}

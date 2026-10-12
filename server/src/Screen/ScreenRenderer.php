<?php

declare(strict_types=1);

namespace App\Screen;

use App\Entity\Dashboard;
use App\Entity\Tile\Tile;
use App\Widget\WidgetRenderer;
use Sensiolabs\GotenbergBundle\Enumeration\ScreenshotFormat;
use Sensiolabs\GotenbergBundle\GotenbergScreenshotInterface;
use Sensiolabs\GotenbergBundle\Processor\InMemoryProcessor;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

/**
 * Produit l'écran affiché par les liseuses, en HTML (aperçu) ou en PNG (liseuses).
 */
class ScreenRenderer
{
    private const TEMPLATE = 'screen/screen.html.twig';

    // L'écran E Ink affiche 16 niveaux de gris : on réduit l'image en amont pour que
    // la liseuse n'ait pas à tramer elle-même les aplats.
    private const GRAY_LEVELS = 16;

    public function __construct(
        private readonly GotenbergScreenshotInterface $gotenberg,
        private readonly Environment $twig,
        private readonly ClockInterface $clock,
        private readonly WidgetRenderer $widgets,
        #[Autowire('%env(int:SCREEN_WIDTH)%')]
        private readonly int $width,
        #[Autowire('%env(int:SCREEN_HEIGHT)%')]
        private readonly int $height,
    ) {
    }

    /**
     * @param Dashboard|null          $dashboard null si la liseuse n'a pas de dashboard assigné
     * @param \DateTimeImmutable|null $at        moment à simuler pour un aperçu (tuiles affichées, heure) ; maintenant par défaut
     */
    public function html(?Dashboard $dashboard, ?\DateTimeImmutable $at = null): string
    {
        return $this->twig->render(self::TEMPLATE, $this->context($dashboard, $at, gotenberg: false));
    }

    /**
     * @param Dashboard|null          $dashboard null si la liseuse n'a pas de dashboard assigné
     * @param \DateTimeImmutable|null $at        moment à simuler pour un aperçu ; maintenant par défaut
     *
     * @return string image PNG en niveaux de gris
     */
    public function png(?Dashboard $dashboard, ?\DateTimeImmutable $at = null): string
    {
        $builder = $this->gotenberg->html()
            ->content(self::TEMPLATE, $this->context($dashboard, $at, gotenberg: true))
            ->width($this->width)
            ->height($this->height)
            ->format(ScreenshotFormat::Png);
        // Appel séparé : PHPStan ne suit le type du processeur que sur une variable
        $builder->processor(new InMemoryProcessor());
        $screenshot = $builder->generate()->process();

        $image = new \Imagick();
        $image->readImageBlob($screenshot);
        $image->transformImageColorspace(\Imagick::COLORSPACE_GRAY);
        $image->posterizeImage(self::GRAY_LEVELS, false);
        $image->setImageFormat('png');
        $image->setImageDepth(8);
        $image->stripImage();

        return $image->getImageBlob();
    }

    /**
     * Contexte du template Twig.
     *
     * @return array{generatedAt: \DateTimeImmutable, dashboard: ?Dashboard, tiles: list<RenderedTile>, gotenberg: bool}
     */
    private function context(?Dashboard $dashboard, ?\DateTimeImmutable $at, bool $gotenberg): array
    {
        $moment = $at ?? $this->clock->now();

        return [
            'generatedAt' => $moment,
            'dashboard' => $dashboard,
            'tiles' => $this->render($dashboard?->tilesVisibleAt($moment) ?? [], $moment),
            'gotenberg' => $gotenberg,
        ];
    }

    /**
     * Rend les widgets des tuiles, sans celles qui n'ont rien à afficher.
     *
     * @param list<Tile> $tiles
     *
     * @return list<RenderedTile>
     */
    private function render(array $tiles, \DateTimeImmutable $moment): array
    {
        $rendered = [];
        foreach ($tiles as $tile) {
            $html = $this->widgets->render($tile, $moment);
            if (null !== $html) {
                $rendered[] = new RenderedTile($tile, $html);
            }
        }

        return $rendered;
    }
}

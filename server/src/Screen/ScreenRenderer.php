<?php

declare(strict_types=1);

namespace App\Screen;

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
        #[Autowire('%env(int:SCREEN_WIDTH)%')]
        private readonly int $width,
        #[Autowire('%env(int:SCREEN_HEIGHT)%')]
        private readonly int $height,
    ) {
    }

    /**
     * @param \DateTimeImmutable|null $at moment à simuler pour un aperçu (widgets affichés, heure) ; maintenant par défaut
     */
    public function html(?\DateTimeImmutable $at = null): string
    {
        return $this->twig->render(self::TEMPLATE, $this->context($at, gotenberg: false));
    }

    /**
     * @param \DateTimeImmutable|null $at moment à simuler pour un aperçu ; maintenant par défaut
     *
     * @return string image PNG en niveaux de gris
     */
    public function png(?\DateTimeImmutable $at = null): string
    {
        $builder = $this->gotenberg->html()
            ->content(self::TEMPLATE, $this->context($at, gotenberg: true))
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
     * @return array{generatedAt: \DateTimeImmutable, gotenberg: bool}
     */
    private function context(?\DateTimeImmutable $at, bool $gotenberg): array
    {
        return ['generatedAt' => $at ?? $this->clock->now(), 'gotenberg' => $gotenberg];
    }
}

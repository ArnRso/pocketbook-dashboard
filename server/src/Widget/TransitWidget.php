<?php

declare(strict_types=1);

namespace App\Widget;

use App\Entity\Device;
use App\Entity\Tile\Tile;
use App\Entity\Tile\TransitTile;
use App\Entity\TransitStop;
use App\Transit\Passage;
use App\Transit\TbmClient;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;

#[AsTaggedItem(TransitTile::WIDGET)]
final class TransitWidget implements WidgetInterface
{
    public function __construct(
        private readonly TbmClient $tbm,
        #[Target('cache.transit')]
        private readonly CacheInterface $transitCache,
        private readonly ClockInterface $clock,
    ) {
    }

    public function template(): string
    {
        return 'widget/transit.html.twig';
    }

    /**
     * Les passages sont ceux du moment présent, même pour l'aperçu d'un autre moment : le temps réel
     * ne porte que sur les deux prochaines heures.
     */
    public function data(Tile $tile, \DateTimeImmutable $at, ?Device $device = null): TransitView
    {
        if (!$tile instanceof TransitTile) {
            throw UnsupportedTileException::for($tile, TransitTile::class);
        }
        $count = $tile->getDepartures() ?? throw new WidgetNotConfiguredException('Nombre de passages non renseigné');
        if ($tile->getStops()->isEmpty()) {
            throw new WidgetNotConfiguredException('Aucun arrêt choisi');
        }

        $now = $this->clock->now();

        return new TransitView(array_values(array_map(
            fn (TransitStop $stop): TransitBoard => new TransitBoard($stop, \array_slice($this->upcoming($stop, $now), 0, $count)),
            $tile->getStops()->toArray(),
        )));
    }

    /**
     * @return list<Passage>
     */
    private function upcoming(TransitStop $stop, \DateTimeImmutable $now): array
    {
        // Une seule requête par arrêt et par minute, quel que soit le nombre de tuiles et de lignes suivies
        $passages = $this->transitCache->get(CacheKey::of('tbm', crc32($stop->getStopArea())), fn (): array => $this->tbm->passages($stop->getStopArea()));

        return array_values(array_filter($passages, static fn (Passage $passage): bool => $passage->line === $stop->getLine()
            && $passage->direction === $stop->getDirection()
            && $passage->time >= $now));
    }
}

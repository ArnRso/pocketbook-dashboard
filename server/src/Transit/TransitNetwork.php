<?php

declare(strict_types=1);

namespace App\Transit;

use App\Entity\TransitStop;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Lignes et arrêts du réseau TBM, relus une fois par jour : de quoi choisir un arrêt dans l'admin.
 */
class TransitNetwork
{
    public function __construct(
        private readonly TbmClient $tbm,
        #[Target('cache.transit_network')]
        private readonly CacheInterface $transitNetworkCache,
    ) {
    }

    /**
     * @throws TransitException
     */
    public function line(string $ref): ?TransitLine
    {
        return $this->lines()[$ref] ?? null;
    }

    /**
     * @throws TransitException
     */
    public function stopArea(string $ref): ?StopArea
    {
        return $this->stopAreas()[$ref] ?? null;
    }

    /**
     * Relève les noms de l'arrêt, de la ligne et de sa destination, pour l'affichage.
     *
     * @throws TransitException si l'arrêt ou la ligne n'existe pas
     */
    public function describe(TransitStop $stop): void
    {
        $area = $this->stopArea($stop->getStopArea());
        $line = $this->line($stop->getLine());
        $destination = $line->destinations[$stop->getDirection()] ?? null;
        if (null === $area || null === $line || null === $destination) {
            throw new TransitException('Arrêt ou ligne inconnu du réseau TBM.');
        }

        $stop->describe($area->name, $line->code, $destination);
    }

    /**
     * Arrêts par nom, avec les lignes qui les desservent pour distinguer les homonymes : « Palmer (E, 59) ».
     *
     * @return array<string, string> libellé => zone d'arrêt
     *
     * @throws TransitException
     */
    public function stopChoices(): array
    {
        $choices = [];
        foreach ($this->stopAreas() as $area) {
            $codes = array_map(fn (string $line): string => $this->line($line)->code ?? '?', $area->lines);
            natsort($codes);
            $label = \sprintf('%s (%s)', $area->name, implode(', ', $codes));
            $choices[isset($choices[$label]) ? \sprintf('%s · %s', $label, $area->ref) : $label] = $area->ref;
        }
        uksort($choices, strnatcasecmp(...));

        return $choices;
    }

    /**
     * Lignes dans chacune de leurs directions : « Tram E → Gare de Blanquefort ».
     *
     * @param string|null $stopArea seulement les lignes qui desservent cette zone d'arrêt
     *
     * @return array<string, string> libellé => ligne et direction (TransitStop::route)
     *
     * @throws TransitException
     */
    public function routeChoices(?string $stopArea = null): array
    {
        $area = null !== $stopArea ? $this->stopArea($stopArea) : null;
        $lines = null !== $stopArea ? array_intersect_key($this->lines(), array_flip($area->lines ?? [])) : $this->lines();

        $choices = [];
        foreach ($lines as $line) {
            foreach ($line->destinations as $direction => $destination) {
                $choices[\sprintf('%s → %s', $line->name, $destination)] = $line->ref.'#'.$direction;
            }
        }
        uksort($choices, strnatcasecmp(...));

        return $choices;
    }

    /**
     * @return array<string, TransitLine>
     *
     * @throws TransitException
     */
    private function lines(): array
    {
        return $this->transitNetworkCache->get('tbm_lines', function (): array {
            $lines = [];
            foreach ($this->tbm->lines() as $line) {
                $lines[$line->ref] = $line;
            }

            return $lines;
        });
    }

    /**
     * @return array<string, StopArea>
     *
     * @throws TransitException
     */
    private function stopAreas(): array
    {
        return $this->transitNetworkCache->get('tbm_stop_areas', function (): array {
            $areas = [];
            foreach ($this->tbm->stopAreas() as $area) {
                $areas[$area->ref] = $area;
            }

            return $areas;
        });
    }
}

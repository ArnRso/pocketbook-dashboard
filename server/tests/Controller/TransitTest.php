<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Admin\TransitStopCrudController;
use App\Entity\Dashboard;
use App\Entity\TransitStop;
use App\EventSubscriber\DescribeTransitStopSubscriber;
use App\Form\TileType;
use App\Repository\TransitStopRepository;
use App\Widget\TransitWidget;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TransitStopCrudController::class)]
#[CoversClass(DescribeTransitStopSubscriber::class)]
#[CoversClass(TileType::class)]
#[CoversClass(TransitWidget::class)]
final class TransitTest extends FunctionalTestCase
{
    public function testStopIsChosenAmongTheStopsAndLinesOfTheNetwork(): void
    {
        $this->logIn();

        $this->client->request('GET', '/admin/transit-stop/new');
        self::assertSelectorTextSame('h1', 'Ajouter un arrêt TBM');
        self::assertSelectorTextSame('select[name="TransitStop[stopArea]"] option[value="bordeaux:StopPoint:BP:TPALMER:LOC"]', 'Palmer (A, E)');
        self::assertSelectorTextSame('select[name="TransitStop[route]"] option[value="bordeaux:Line:163:LOC#0"]', 'Tram E → Gare de Blanquefort');

        $this->client->submitForm('Créer', [
            'TransitStop[stopArea]' => 'bordeaux:StopPoint:BP:TPALMER:LOC',
            'TransitStop[route]' => 'bordeaux:Line:163:LOC#0',
        ]);

        self::assertResponseRedirects();
        $stop = static::getContainer()->get(TransitStopRepository::class)->findOneBy([]);
        self::assertInstanceOf(TransitStop::class, $stop);
        self::assertSame('Palmer · E → Gare de Blanquefort', (string) $stop);

        $this->client->request('GET', '/admin/transit-stop');

        self::assertSelectorTextSame('td[data-column="stopName"]', 'Palmer');
        self::assertSelectorTextSame('td[data-column="lineCode"]', 'E');
        self::assertSelectorTextSame('td[data-column="destination"]', 'Gare de Blanquefort');
    }

    public function testLineMustServeTheStop(): void
    {
        $this->logIn();

        $this->client->request('GET', '/admin/transit-stop/new');
        $this->client->submitForm('Créer', [
            'TransitStop[stopArea]' => 'bordeaux:StopPoint:BP:TPALMER:LOC',
            'TransitStop[route]' => 'bordeaux:Line:01:LOC#0',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'La ligne Lianes 1 ne dessert pas cet arrêt.');
        self::assertSame(0, static::getContainer()->get(TransitStopRepository::class)->count());
    }

    public function testTileShowsTheNextPassagesOfItsStops(): void
    {
        $dashboard = (new Dashboard())->setName('Entrée');
        $tram = (new TransitStop())->setStopArea('bordeaux:StopPoint:BP:TPALMER:LOC')->setRoute('bordeaux:Line:163:LOC#0')->describe('Palmer', 'E', 'Gare de Blanquefort');
        $this->entityManager()->persist($dashboard);
        $this->entityManager()->persist($tram);
        $this->entityManager()->flush();
        $this->logIn();

        $this->client->request('GET', \sprintf('/admin/dashboard/%d/tiles/new/transit', (int) $dashboard->getId()));
        self::assertSelectorTextSame(\sprintf('label[for="tile_stops_%d"]', (int) $tram->getId()), 'Palmer · E → Gare de Blanquefort');
        self::assertInputValueSame('tile[departures]', '3');
        $this->client->submitForm('Enregistrer', ['tile[stops]' => [(string) $tram->getId()]]);
        self::assertResponseRedirects();

        $this->client->request('GET', \sprintf('/preview?dashboard=%d', (int) $dashboard->getId()));

        self::assertSelectorTextSame('.tile-transit .transit-line', 'E');
        self::assertSelectorTextSame('.tile-transit .transit-stop', 'Palmer → Gare de Blanquefort');
        self::assertSelectorTextSame('.tile-transit .transit-times', '13:24 13:32 13:42');
        // Horaire théorique, sans temps réel
        self::assertSelectorTextSame('.tile-transit .transit-times .muted', '13:32');
    }

    public function testStopIsRequiredByTheTile(): void
    {
        $dashboard = (new Dashboard())->setName('Entrée');
        $this->entityManager()->persist($dashboard);
        $this->entityManager()->flush();
        $this->logIn();

        $this->client->request('GET', \sprintf('/admin/dashboard/%d/tiles/new/transit', (int) $dashboard->getId()));
        $this->client->submitForm('Enregistrer');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="tile"]', 'Choisis au moins un arrêt.');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Admin\DashboardCrudController;
use App\Entity\Dashboard;
use App\Entity\DashboardLayout;
use App\Entity\Place;
use App\Entity\Tile\ClockTile;
use App\Entity\Tile\CurrentWeatherTile;
use App\Entity\Tile\HourlyForecastTile;
use App\Entity\Tile\RainTile;
use App\Entity\Tile\Tile;
use App\Entity\Tile\TileSize;
use App\Entity\Tile\TileWidth;
use App\Form\TileType;
use App\Repository\DashboardRepository;
use App\Screen\AutoLayout;
use App\Screen\ScreenRenderer;
use App\Screen\TileEditor;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DashboardCrudController::class)]
#[CoversClass(TileEditor::class)]
#[CoversClass(TileType::class)]
#[CoversClass(ScreenRenderer::class)]
#[CoversClass(AutoLayout::class)]
final class AutoLayoutEditorTest extends FunctionalTestCase
{
    public function testScreenIsFilledByTheVisibleTilesInTheirOrder(): void
    {
        $dashboard = $this->createAutoDashboard();
        $this->logIn();

        $crawler = $this->client->request('GET', $this->preview($dashboard, '08:00'));

        self::assertSelectorNotExists('.grid');
        self::assertSame(['flex-grow: 1', 'flex-grow: 2', 'flex-grow: 3'], $crawler->filter('.line')->each(static fn ($line): ?string => $line->attr('style')));
        self::assertSame([['tile tile-clock'], ['tile tile-current_weather', 'tile tile-rain'], ['tile tile-hourly_forecast']], $crawler->filter('.line')->each(
            static fn ($line): array => $line->filter('.tile')->each(static fn ($tile): ?string => $tile->attr('class')),
        ));

        // Le soir, la météo actuelle est masquée : la pluie prend toute sa ligne
        $crawler = $this->client->request('GET', $this->preview($dashboard, '21:00'));

        self::assertSame([['tile tile-clock'], ['tile tile-rain'], ['tile tile-hourly_forecast']], $crawler->filter('.line')->each(
            static fn ($line): array => $line->filter('.tile')->each(static fn ($tile): ?string => $tile->attr('class')),
        ));
        self::assertSame(['flex-grow: 1', 'flex-grow: 1', 'flex-grow: 3'], $crawler->filter('.line')->each(static fn ($line): ?string => $line->attr('style')));
    }

    public function testEditorListsEveryTileInOrder(): void
    {
        $dashboard = $this->createAutoDashboard();
        $this->logIn();

        $this->client->request('GET', $this->url($dashboard, '/grid?day=1&time=21:00'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Tuiles — Entrée');
        self::assertSelectorNotExists('[data-grid-editor]');
        self::assertSelectorCount(4, '.tile-order > li');
        self::assertSelectorTextContains('.tile-order > li:nth-child(2)', 'Météo actuelle · Maison');
        self::assertSelectorTextContains('.tile-order > li:nth-child(2)', 'Demi-largeur · hauteur moyenne · Tous les jours, 06:00–20:00');
        self::assertSelectorTextContains('.tile-order > li:nth-child(2) .badge', 'Masquée à ce moment');
        self::assertSelectorNotExists('.tile-order > li:nth-child(1) .badge');
        self::assertSelectorExists('.tile-order > li:first-child button[title="Monter"][disabled]');
        self::assertSelectorExists('.tile-order > li:last-child button[title="Descendre"][disabled]');
        self::assertSelectorNotExists('.tile-order > li:nth-child(2) button[disabled]');
    }

    public function testTileIsMovedUpAndDown(): void
    {
        $dashboard = $this->createAutoDashboard();
        $this->logIn();

        $crawler = $this->client->request('GET', $this->url($dashboard, '/grid?day=1&time=21:00'));
        $this->client->submit($crawler->filter('.tile-order > li:nth-child(3) button[title="Monter"]')->form());

        self::assertResponseRedirects($this->url($dashboard, '/grid?day=1&time=21:00'));
        self::assertSame(['clock', 'rain', 'current_weather', 'hourly_forecast'], $this->widgets($dashboard));

        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->filter('.tile-order > li:nth-child(1) button[title="Descendre"]')->form());

        self::assertSame(['rain', 'clock', 'current_weather', 'hourly_forecast'], $this->widgets($dashboard));
    }

    public function testMoveRequiresTheCsrfToken(): void
    {
        $dashboard = $this->createAutoDashboard();
        $this->logIn();
        $rain = $dashboard->orderedTiles()[2] ?? self::fail('Pas de tuile Pluie.');

        $this->client->request('POST', $this->url($dashboard, \sprintf('/tiles/%d/shift/up', (int) $rain->getId())), ['_token' => 'invalide']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['clock', 'current_weather', 'rain', 'hourly_forecast'], $this->widgets($dashboard));
    }

    public function testWidthAndHeightAreSetInTheTileForm(): void
    {
        $dashboard = $this->createAutoDashboard();
        $clock = $dashboard->orderedTiles()[0] ?? self::fail('Pas de tuile Date et heure.');
        $this->logIn();

        $this->client->request('GET', $this->url($dashboard, \sprintf('/tiles/%d/edit', (int) $clock->getId())));
        self::assertSelectorExists('select[name="tile[autoWidth]"] option[value="full"][selected]');
        self::assertSelectorExists('select[name="tile[autoSize]"] option[value="1"][selected]');

        $this->client->submitForm('Enregistrer', ['tile[autoWidth]' => 'half', 'tile[autoSize]' => '3']);

        self::assertResponseRedirects($this->url($dashboard, '/grid'));
        $saved = $this->reload($dashboard)->orderedTiles()[0] ?? self::fail('Pas de tuile Date et heure.');
        self::assertSame([TileWidth::Half, TileSize::Large], [$saved->getAutoWidth(), $saved->getAutoSize()]);
    }

    public function testWidthAndHeightAreNotAskedInGridLayout(): void
    {
        $dashboard = $this->createDashboard();
        $this->logIn();

        $this->client->request('GET', $this->url($dashboard, '/tiles/new/clock'));

        self::assertSelectorNotExists('select[name="tile[autoWidth]"]');
        self::assertSelectorNotExists('select[name="tile[autoSize]"]');
    }

    public function testNewTileIsAddedLastEvenWithoutRoomOnTheGrid(): void
    {
        // Toute la grille est occupée de 6 h à 20 h
        $dashboard = $this->createDashboard()->setLayout(DashboardLayout::Auto);
        $this->entityManager()->flush();
        $this->logIn();

        $this->client->request('GET', $this->url($dashboard, '/tiles/new/clock'));
        $this->client->submitForm('Enregistrer');

        self::assertResponseRedirects($this->url($dashboard, '/grid'));
        self::assertSame(['clock', 'current_weather', 'rain', 'hourly_forecast', 'commute', 'clock'], $this->widgets($dashboard));
    }

    public function testLayoutIsChosenInTheDashboardForm(): void
    {
        $dashboard = $this->createAutoDashboard();
        $this->logIn();

        $this->client->request('GET', '/admin/dashboard');
        self::assertSelectorTextContains('td[data-column="layout"]', 'Automatique');

        $this->client->request('GET', \sprintf('/admin/dashboard/%d/edit', (int) $dashboard->getId()));
        $this->client->submitForm('Sauvegarder les modifications', ['Dashboard[layout]' => 'grid']);

        self::assertResponseRedirects();
        self::assertSame(DashboardLayout::Grid, $this->reload($dashboard)->getLayout());
    }

    /**
     * Entrée : date, météo actuelle (de 6 h à 20 h) et pluie côte à côte, prévisions.
     */
    private function createAutoDashboard(): Dashboard
    {
        $home = (new Place())->setLabel('Maison')->setAddress('Place Bellecour, Lyon')->setLatitude(45.7576)->setLongitude(4.832);
        $current = (new CurrentWeatherTile())->setPlace($home)->setAutoWidth(TileWidth::Half);
        $current->getSchedule()->setStartTime(new \DateTimeImmutable('06:00'))->setEndTime(new \DateTimeImmutable('20:00'));

        $dashboard = (new Dashboard())
            ->setName('Entrée')
            ->addTile(new ClockTile())
            ->addTile($current)
            ->addTile((new RainTile())->setPlace($home)->setAutoWidth(TileWidth::Half))
            ->addTile((new HourlyForecastTile())->setPlace($home));

        $this->entityManager()->persist($home);
        $this->entityManager()->persist($dashboard);
        $this->entityManager()->flush();

        return $dashboard;
    }

    /**
     * @return list<string> widgets du dashboard dans l'ordre de la mise en page, relus en base
     */
    private function widgets(Dashboard $dashboard): array
    {
        return array_map(static fn (Tile $tile): string => $tile->widget(), $this->reload($dashboard)->orderedTiles());
    }

    private function reload(Dashboard $dashboard): Dashboard
    {
        $this->entityManager()->clear();
        $reloaded = static::getContainer()->get(DashboardRepository::class)->find($dashboard->getId());
        self::assertInstanceOf(Dashboard::class, $reloaded);

        return $reloaded;
    }

    private function preview(Dashboard $dashboard, string $time): string
    {
        return \sprintf('/preview?dashboard=%d&day=1&time=%s', (int) $dashboard->getId(), $time);
    }

    private function url(Dashboard $dashboard, string $path): string
    {
        return \sprintf('/admin/dashboard/%d%s', (int) $dashboard->getId(), $path);
    }
}

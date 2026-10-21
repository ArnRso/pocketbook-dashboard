<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Admin\DashboardCrudController;
use App\Entity\Dashboard;
use App\Entity\Tile\ClockTile;
use App\Entity\Tile\RainTile;
use App\Entity\Tile\Tile;
use App\Form\ScheduleType;
use App\Form\TileType;
use App\Repository\TileRepository;
use App\Screen\TileEditor;
use App\Screen\TileLayout;
use App\Screen\TileTypeChoice;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;

#[CoversClass(DashboardCrudController::class)]
#[CoversClass(TileEditor::class)]
#[CoversClass(TileLayout::class)]
#[CoversClass(TileTypeChoice::class)]
#[CoversClass(TileType::class)]
#[CoversClass(ScheduleType::class)]
final class GridEditorTest extends FunctionalTestCase
{
    public function testGridShowsTheTilesVisibleAtTheChosenMoment(): void
    {
        $dashboard = $this->createDashboard();
        $this->logIn();

        $crawler = $this->client->request('GET', $this->url($dashboard, '/grid'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Grille — Principal');
        self::assertSelectorCount(5, '.grid-stack-item');
        $hourly = $this->item($crawler, 'Prévisions heure par heure · Maison');
        self::assertSame(['0', '7', '12', '4'], [$hourly->attr('gs-x'), $hourly->attr('gs-y'), $hourly->attr('gs-w'), $hourly->attr('gs-h')]);
        self::assertSelectorTextContains('.grid-stack', 'Tous les jours, 06:00–20:00');
        self::assertSelectorCount(\count(Tile::TYPES), '.tile-types .dropdown-item');
        self::assertSelectorExists(\sprintf('img[src="/preview.png?dashboard=%d"]', (int) $dashboard->getId()));

        $this->client->request('GET', $this->url($dashboard, '/grid?time=21:00'));

        self::assertSelectorCount(4, '.grid-stack-item');
        self::assertSelectorTextSame('h2.h5', 'Masquées à ce moment');
        self::assertSelectorTextContains('h2.h5 + ul', 'Trajet · Maison → Travail (Tous les jours, 06:00–20:00)');
    }

    public function testTileIsMovedAndResized(): void
    {
        $dashboard = $this->createDashboard();
        $clock = $this->tile($dashboard, ClockTile::class);
        $this->logIn();
        $this->client->request('GET', $this->url($dashboard, '/grid'));

        $this->moveTile($dashboard, $clock, ['column' => 7, 'row' => 1, 'width' => 6, 'height' => 2]);

        self::assertResponseStatusCodeSame(204);
        $saved = static::getContainer()->get(TileRepository::class)->find($clock->getId());
        self::assertSame([7, 1, 6, 2], [$saved?->getColumn(), $saved?->getRow(), $saved?->getWidth(), $saved?->getHeight()]);
    }

    public function testOverlappingMoveIsRefused(): void
    {
        $dashboard = $this->createDashboard();
        $clock = $this->tile($dashboard, ClockTile::class);
        $this->logIn();
        $this->client->request('GET', $this->url($dashboard, '/grid'));

        $this->moveTile($dashboard, $clock, ['column' => 1, 'row' => 1, 'width' => 12, 'height' => 3]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonStringEqualsJsonString(
            (string) json_encode(['message' => 'Cette tuile chevauche « Météo actuelle · Maison » à des horaires communs.']),
            (string) $this->client->getResponse()->getContent(),
        );
        self::assertSame(2, static::getContainer()->get(TileRepository::class)->find($clock->getId())?->getHeight());
    }

    public function testMoveOutsideTheGridIsRefused(): void
    {
        $dashboard = $this->createDashboard();
        $clock = $this->tile($dashboard, ClockTile::class);
        $this->logIn();
        $this->client->request('GET', $this->url($dashboard, '/grid'));

        $this->moveTile($dashboard, $clock, ['column' => 13, 'row' => 1, 'width' => 1, 'height' => 1]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testMoveRequiresTheCsrfToken(): void
    {
        $dashboard = $this->createDashboard();
        $clock = $this->tile($dashboard, ClockTile::class);
        $this->logIn();

        $this->client->request('POST', $this->url($dashboard, \sprintf('/tiles/%d/layout', (int) $clock->getId())), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'invalide',
        ], content: '{"column":1,"row":1,"width":6,"height":2}');

        self::assertResponseStatusCodeSame(403);
    }

    public function testTileOfAnotherDashboardIsNotFound(): void
    {
        $dashboard = $this->createDashboard();
        $other = new Dashboard()->setName('Cuisine');
        $this->entityManager()->persist($other);
        $this->entityManager()->flush();
        $this->logIn();

        $this->client->request('GET', $this->url($other, \sprintf('/tiles/%d/edit', (int) $this->tile($dashboard, ClockTile::class)->getId())));

        self::assertResponseStatusCodeSame(404);
    }

    public function testNewTileIsPlacedOnTheFirstFreeSpotAtItsHours(): void
    {
        $dashboard = $this->createDashboard();
        $this->logIn();

        $this->client->request('GET', $this->url($dashboard, '/tiles/new/rain'));
        self::assertSelectorTextContains('h1', 'Ajouter une tuile « Pluie »');
        self::assertSelectorExists('select[name="tile[place]"]');
        self::assertSelectorNotExists('select[name="tile[trip]"]');

        // Toute la grille est occupée de 6 h à 20 h : la place du trajet est libre le soir
        $this->client->submitForm('Enregistrer', [
            'tile[place]' => (string) $this->tile($dashboard, RainTile::class)->getPlace()?->getId(),
            'tile[schedule][startTime]' => '21:00',
            'tile[schedule][endTime]' => '23:00',
        ]);

        self::assertResponseRedirects($this->url($dashboard, '/grid'));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Tuile « Pluie · Maison » ajoutée.');
        $added = static::getContainer()->get(TileRepository::class)->findOneBy(['row' => 12, 'column' => 1, 'height' => 2]);
        self::assertInstanceOf(RainTile::class, $added);
        self::assertSame('Tous les jours, 21:00–23:00', (string) $added->getSchedule());
    }

    public function testBridgeTileShowsTheChosenNumberOfDays(): void
    {
        $dashboard = new Dashboard()->setName('Pont');
        $this->entityManager()->persist($dashboard);
        $this->entityManager()->flush();
        $this->logIn();

        $this->client->request('GET', $this->url($dashboard, '/tiles/new/chaban'));
        self::assertInputValueSame('tile[days]', '7');
        $this->client->submitForm('Enregistrer', ['tile[days]' => '14']);
        self::assertResponseRedirects($this->url($dashboard, '/grid'));

        $this->client->request('GET', \sprintf('/preview?dashboard=%d', (int) $dashboard->getId()));
        self::assertSelectorTextContains('.tile-chaban .tile-title', 'Pont Chaban-Delmas · 14 jours');
        self::assertSelectorCount(4, '.chaban-closures li');
        self::assertSelectorTextContains('.chaban-closures li', 'Sam. 17 oct. 10:34 – 11:57 Silver Dawn');
    }

    public function testNewTileWithoutFreeSpotIsRefused(): void
    {
        $dashboard = $this->createDashboard();
        $this->logIn();

        $this->client->request('GET', $this->url($dashboard, '/tiles/new/clock'));
        $this->client->submitForm('Enregistrer');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.alert-danger', 'Plus de place libre de 12 × 2 cases à ces horaires');
        self::assertCount(5, static::getContainer()->get(TileRepository::class)->findAll());
    }

    public function testNewTileSettingsAreRequired(): void
    {
        $dashboard = $this->createDashboard();
        $this->logIn();

        $this->client->request('GET', $this->url($dashboard, '/tiles/new/commute'));
        $this->client->submitForm('Enregistrer');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="tile"]', 'Choisis un trajet.');
    }

    public function testUnknownTileTypeIsNotFound(): void
    {
        $dashboard = $this->createDashboard();
        $this->logIn();

        $this->client->request('GET', $this->url($dashboard, '/tiles/new/horloge'));

        self::assertResponseStatusCodeSame(404);
    }

    public function testTileScheduleIsEdited(): void
    {
        $dashboard = $this->createDashboard();
        $clock = $this->tile($dashboard, ClockTile::class);
        $this->logIn();

        $crawler = $this->client->request('GET', $this->url($dashboard, \sprintf('/tiles/%d/edit', (int) $clock->getId())));
        self::assertSelectorTextContains('h1', 'Modifier « Date et heure »');
        $form = $crawler->selectButton('Enregistrer')->form([
            'tile[schedule][startTime]' => '06:00',
            'tile[schedule][endTime]' => '09:30',
        ]);
        // Samedi et dimanche décochés
        self::untick($form, 'tile[schedule][weekdays][5]');
        self::untick($form, 'tile[schedule][weekdays][6]');
        $this->client->submit($form);

        self::assertResponseRedirects($this->url($dashboard, '/grid'));
        $saved = static::getContainer()->get(TileRepository::class)->find($clock->getId());
        self::assertSame('Lun, Mar, Mer, Jeu, Ven, 06:00–09:30', (string) $saved?->getSchedule());
    }

    public function testInvalidScheduleIsReported(): void
    {
        $dashboard = $this->createDashboard();
        $clock = $this->tile($dashboard, ClockTile::class);
        $this->logIn();

        $this->client->request('GET', $this->url($dashboard, \sprintf('/tiles/%d/edit', (int) $clock->getId())));
        $this->client->submitForm('Enregistrer', [
            'tile[schedule][startTime]' => '10:00',
            'tile[schedule][endTime]' => '09:00',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="tile"]', 'L\'heure de fin doit être après l\'heure de début.');
    }

    public function testTileIsDeleted(): void
    {
        $dashboard = $this->createDashboard();
        $this->logIn();

        $crawler = $this->client->request('GET', $this->url($dashboard, '/grid'));
        $this->client->submit($this->item($crawler, 'Date et heure')->filter('form')->form());

        self::assertResponseRedirects($this->url($dashboard, '/grid'));
        self::assertCount(4, static::getContainer()->get(TileRepository::class)->findAll());
    }

    public function testDeletionRequiresTheCsrfToken(): void
    {
        $dashboard = $this->createDashboard();
        $clock = $this->tile($dashboard, ClockTile::class);
        $this->logIn();

        $this->client->request('POST', $this->url($dashboard, \sprintf('/tiles/%d/delete', (int) $clock->getId())), ['_token' => 'invalide']);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(5, static::getContainer()->get(TileRepository::class)->findAll());
    }

    private static function untick(Form $form, string $name): void
    {
        $checkbox = $form->get($name);
        self::assertInstanceOf(ChoiceFormField::class, $checkbox);
        $checkbox->untick();
    }

    private function url(Dashboard $dashboard, string $path): string
    {
        return \sprintf('/admin/dashboard/%d%s', (int) $dashboard->getId(), $path);
    }

    /**
     * @template T of Tile
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function tile(Dashboard $dashboard, string $class): Tile
    {
        foreach ($dashboard->getTiles() as $tile) {
            if ($tile instanceof $class) {
                return $tile;
            }
        }

        self::fail(\sprintf('Aucune tuile %s.', $class));
    }

    private function item(Crawler $crawler, string $description): Crawler
    {
        return $crawler->filter('.grid-stack-item')->reduce(static fn (Crawler $item): bool => str_contains($item->filter('strong')->text(), $description));
    }

    /**
     * @param array{column: int, row: int, width: int, height: int} $layout
     */
    private function moveTile(Dashboard $dashboard, Tile $tile, array $layout): void
    {
        $token = $this->client->getCrawler()->filter('[data-grid-editor]')->attr('data-csrf');

        $this->client->request('POST', $this->url($dashboard, \sprintf('/tiles/%d/layout', (int) $tile->getId())), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => (string) $token,
        ], content: json_encode($layout, \JSON_THROW_ON_ERROR));
    }
}

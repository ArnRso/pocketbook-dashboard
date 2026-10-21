<?php

declare(strict_types=1);

namespace App\Tests\Widget;

use App\Chaban\BridgeClosure;
use App\Chaban\ChabanClient;
use App\Entity\Tile\ChabanTile;
use App\Entity\Tile\ClockTile;
use App\Widget\CacheKey;
use App\Widget\ChabanView;
use App\Widget\ChabanWidget;
use App\Widget\UnsupportedTileException;
use App\Widget\WidgetNotConfiguredException;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\SerializerInterface;

#[CoversClass(ChabanWidget::class)]
#[CoversClass(ChabanView::class)]
#[CoversClass(CacheKey::class)]
final class ChabanWidgetTest extends KernelTestCase
{
    public function testClosuresOfTheComingCalendarDays(): void
    {
        // Du lundi 12 au dimanche 18 : la fermeture du lundi 19 n'en fait pas partie
        $view = $this->widget()->data(new ChabanTile()->setDays(7), self::at('2026-10-12 08:00'));

        self::assertSame(7, $view->days);
        self::assertSame(['2026-10-17 10:34'], array_map(self::start(...), $view->closures));
        self::assertNull($view->ongoing);

        $view = $this->widget()->data(new ChabanTile()->setDays(8), self::at('2026-10-12 08:00'));

        self::assertSame(['2026-10-17 10:34', '2026-10-19 11:34'], array_map(self::start(...), $view->closures));
    }

    public function testPastClosuresOfTheDayAreNotShown(): void
    {
        $view = $this->widget()->data(new ChabanTile(), self::at('2026-10-17 12:00'));

        // La fermeture de 10:34 à 11:57 est passée
        self::assertSame(['2026-10-19 11:34', '2026-10-22 16:34', '2026-10-23 16:49'], array_map(self::start(...), $view->closures));
    }

    public function testNumberOfDaysIsConfigurable(): void
    {
        $view = $this->widget()->data(new ChabanTile()->setDays(14), self::at('2026-10-12 08:00'));

        self::assertSame(['2026-10-17 10:34', '2026-10-19 11:34', '2026-10-22 16:34', '2026-10-23 16:49'], array_map(self::start(...), $view->closures));
    }

    public function testOngoingClosure(): void
    {
        $view = $this->widget()->data(new ChabanTile(), self::at('2026-10-17 11:00'));

        self::assertSame('2026-10-17 10:34', null !== $view->ongoing ? self::start($view->ongoing) : null);
        self::assertSame('2026-10-17 10:34', self::start($view->closures[0] ?? self::fail('Aucune fermeture.')));
    }

    public function testClosuresAreFetchedOncePerDayForEveryTile(): void
    {
        $http = $this->http();
        $widget = $this->widget($http);

        $widget->data(new ChabanTile()->setDays(3), self::at('2026-10-12 08:00'));
        $widget->data(new ChabanTile()->setDays(30), self::at('2026-10-12 18:00'));
        $widget->data(new ChabanTile(), self::at('2026-10-13 08:00'));

        self::assertSame(2, $http->getRequestsCount());
        self::assertSame('widget/chaban.html.twig', $widget->template());
    }

    public function testMissingNumberOfDaysIsReported(): void
    {
        $this->expectException(WidgetNotConfiguredException::class);
        $this->expectExceptionMessageIs('Nombre de jours non renseigné');

        $this->widget()->data(new ChabanTile()->setDays(null), self::at('2026-10-12 08:00'));
    }

    public function testOtherTilesAreRejected(): void
    {
        $this->expectException(UnsupportedTileException::class);

        $this->widget()->data(new ClockTile(), self::at('2026-10-12 08:00'));
    }

    private function widget(?MockHttpClient $http = null): ChabanWidget
    {
        return new ChabanWidget(
            new ChabanClient($http ?? $this->http(), self::getContainer()->get(SerializerInterface::class)),
            new ArrayAdapter(),
        );
    }

    private function http(): MockHttpClient
    {
        $json = file_get_contents(__DIR__.'/../fixtures/chaban-closures.json');
        self::assertIsString($json);

        return new MockHttpClient(static fn (): MockResponse => new MockResponse($json), 'https://opendata.bordeaux-metropole.fr');
    }

    private static function at(string $moment): \DateTimeImmutable
    {
        return new \DateTimeImmutable($moment, new \DateTimeZone('Europe/Paris'));
    }

    private static function start(BridgeClosure $closure): string
    {
        return $closure->start->format('Y-m-d H:i');
    }
}

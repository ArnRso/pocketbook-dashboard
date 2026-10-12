<?php

declare(strict_types=1);

namespace App\Tests\Widget;

use App\Entity\Device;
use App\Entity\Tile\ClockTile;
use App\Entity\Tile\Tile;
use App\Widget\NothingToShowException;
use App\Widget\WidgetInterface;
use App\Widget\WidgetNotConfiguredException;
use App\Widget\WidgetRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(WidgetRenderer::class)]
#[CoversClass(WidgetNotConfiguredException::class)]
final class WidgetRendererTest extends TestCase
{
    public function testTileIsRenderedByTheWidgetOfItsTypeForTheGivenMoment(): void
    {
        $widget = new FakeWidget(static fn (Tile $tile, \DateTimeImmutable $at): object => new \ArrayObject(['value' => $tile->widget().' '.$at->format('H:i')]));

        self::assertSame('valeur : clock 08:00', $this->renderer('clock', $widget)->render(new ClockTile(), new \DateTimeImmutable('2026-10-12 08:00')));
    }

    public function testDisplayableErrorReplacesTheTileContent(): void
    {
        $widget = new FakeWidget(static fn (): never => throw new WidgetNotConfiguredException('Rien à afficher'));

        self::assertSame('Date et heure indisponible : Rien à afficher', $this->renderer('clock', $widget)->render(new ClockTile(), new \DateTimeImmutable('2026-10-12 08:00')));
    }

    public function testWidgetWithNothingToShowIsNotRendered(): void
    {
        $widget = new FakeWidget(static fn (): never => throw new NothingToShowException('Aucun rappel en cours.'));

        self::assertNull($this->renderer('clock', $widget)->render(new ClockTile(), new \DateTimeImmutable('2026-10-12 08:00')));
    }

    public function testUnexpectedErrorsAreNotHidden(): void
    {
        $widget = new FakeWidget(static fn (): never => throw new \LogicException('Bug'));

        $this->expectException(\LogicException::class);

        $this->renderer('clock', $widget)->render(new ClockTile(), new \DateTimeImmutable('2026-10-12 08:00'));
    }

    public function testTileWithoutWidgetIsAnError(): void
    {
        $this->expectException(ServiceNotFoundException::class);

        $this->renderer('autre', new FakeWidget(static fn (): object => new \stdClass()))->render(new ClockTile(), new \DateTimeImmutable('2026-10-12 08:00'));
    }

    private function renderer(string $name, WidgetInterface $widget): WidgetRenderer
    {
        /** @var ServiceLocator<WidgetInterface> $locator */
        $locator = new ServiceLocator([$name => static fn (): WidgetInterface => $widget]);

        return new WidgetRenderer($locator, new Environment(new ArrayLoader([
            'fake.html.twig' => 'valeur : {{ data.value }}',
            'widget/_unavailable.html.twig' => '{{ tile.describe }} indisponible : {{ message }}',
        ])), new NullLogger());
    }
}

/**
 * @internal
 */
final readonly class FakeWidget implements WidgetInterface
{
    /**
     * @param \Closure(Tile, \DateTimeImmutable): object $data
     */
    public function __construct(
        private \Closure $data,
    ) {
    }

    public function template(): string
    {
        return 'fake.html.twig';
    }

    public function data(Tile $tile, \DateTimeImmutable $at, ?Device $device = null): object
    {
        return ($this->data)($tile, $at);
    }
}

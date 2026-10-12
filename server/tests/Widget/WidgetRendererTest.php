<?php

declare(strict_types=1);

namespace App\Tests\Widget;

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
    public function testWidgetIsRenderedWithItsDataForTheGivenMoment(): void
    {
        $widget = new FakeWidget(static fn (\DateTimeImmutable $at): object => new \ArrayObject(['value' => $at->format('H:i')]));

        self::assertSame('valeur : 08:00', $this->renderer($widget)->render('fake', new \DateTimeImmutable('2026-10-12 08:00')));
    }

    public function testDisplayableErrorReplacesTheWidget(): void
    {
        $widget = new FakeWidget(static fn (): never => throw new WidgetNotConfiguredException('Rien à afficher'));

        self::assertSame('fake indisponible : Rien à afficher', $this->renderer($widget)->render('fake', new \DateTimeImmutable('2026-10-12 08:00')));
    }

    public function testUnexpectedErrorsAreNotHidden(): void
    {
        $widget = new FakeWidget(static fn (): never => throw new \LogicException('Bug'));

        $this->expectException(\LogicException::class);

        $this->renderer($widget)->render('fake', new \DateTimeImmutable('2026-10-12 08:00'));
    }

    public function testUnknownWidgetIsAnError(): void
    {
        $this->expectException(ServiceNotFoundException::class);

        $this->renderer(new FakeWidget(static fn (): object => new \stdClass()))->render('inconnu', new \DateTimeImmutable('2026-10-12 08:00'));
    }

    private function renderer(WidgetInterface $widget): WidgetRenderer
    {
        /** @var ServiceLocator<WidgetInterface> $locator */
        $locator = new ServiceLocator(['fake' => static fn (): WidgetInterface => $widget]);

        return new WidgetRenderer($locator, new Environment(new ArrayLoader([
            'fake.html.twig' => 'valeur : {{ data.value }}',
            'widget/_unavailable.html.twig' => '{{ name }} indisponible : {{ message }}',
        ])), new NullLogger());
    }
}

/**
 * @internal
 */
final readonly class FakeWidget implements WidgetInterface
{
    /**
     * @param \Closure(\DateTimeImmutable): object $data
     */
    public function __construct(
        private \Closure $data,
    ) {
    }

    public function template(): string
    {
        return 'fake.html.twig';
    }

    public function data(\DateTimeImmutable $at): object
    {
        return ($this->data)($at);
    }
}

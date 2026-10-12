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
    public function testWidgetIsRenderedWithItsData(): void
    {
        $widget = new FakeWidget(static fn (): object => new \ArrayObject(['value' => 42]));

        self::assertSame('valeur : 42', $this->renderer($widget)->render('fake'));
    }

    public function testDisplayableErrorReplacesTheWidget(): void
    {
        $widget = new FakeWidget(static fn (): never => throw new WidgetNotConfiguredException('Rien à afficher'));

        self::assertSame('fake indisponible : Rien à afficher', $this->renderer($widget)->render('fake'));
    }

    public function testUnexpectedErrorsAreNotHidden(): void
    {
        $widget = new FakeWidget(static fn (): never => throw new \LogicException('Bug'));

        $this->expectException(\LogicException::class);

        $this->renderer($widget)->render('fake');
    }

    public function testUnknownWidgetIsAnError(): void
    {
        $this->expectException(ServiceNotFoundException::class);

        $this->renderer(new FakeWidget(static fn (): object => new \stdClass()))->render('inconnu');
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
     * @param \Closure(): object $data
     */
    public function __construct(
        private \Closure $data,
    ) {
    }

    public function template(): string
    {
        return 'fake.html.twig';
    }

    public function data(): object
    {
        return ($this->data)();
    }
}

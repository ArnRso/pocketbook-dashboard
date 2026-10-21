<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Form\QuarterHourTransformer;
use App\Form\QuarterHourType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

#[CoversClass(QuarterHourType::class)]
#[CoversClass(QuarterHourTransformer::class)]
final class QuarterHourTypeTest extends TestCase
{
    private FormFactoryInterface $factory;

    protected function setUp(): void
    {
        $this->factory = Forms::createFormFactory();
    }

    public function testChoicesCoverTheDayByQuarterHour(): void
    {
        $choices = QuarterHourType::choices();

        self::assertCount(96, $choices);
        self::assertSame('00:00', array_key_first($choices));
        self::assertSame('23:45', array_key_last($choices));
        self::assertSame(['08:00', '08:15', '08:30', '08:45'], \array_slice(array_values($choices), 32, 4));
    }

    public function testStringInput(): void
    {
        $form = $this->factory->create(QuarterHourType::class);

        $form->submit('08:15');

        self::assertTrue($form->isSynchronized());
        self::assertSame('08:15', $form->getData());
    }

    public function testDateTimeInput(): void
    {
        $form = $this->factory->create(QuarterHourType::class, new \DateTimeImmutable('1970-01-01 06:30'), ['input' => 'datetime_immutable']);
        self::assertSame('06:30', $form->getViewData());

        $form->submit('21:45');

        self::assertTrue($form->isSynchronized());
        self::assertEquals(new \DateTimeImmutable('1970-01-01 21:45'), $form->getData());
    }

    public function testExistingTimeIsShownAtThePreviousQuarterHour(): void
    {
        $form = $this->factory->create(QuarterHourType::class, new \DateTimeImmutable('1970-01-01 23:59'), ['input' => 'datetime_immutable']);

        self::assertSame('23:45', $form->getViewData());
    }

    public function testEmptyValue(): void
    {
        $form = $this->factory->create(QuarterHourType::class, null, ['input' => 'datetime_immutable', 'required' => false]);

        $form->submit('');

        self::assertTrue($form->isSynchronized());
        self::assertNull($form->getData());
    }

    public function testValueOutsideTheListIsRejected(): void
    {
        $form = $this->factory->create(QuarterHourType::class, null, ['input' => 'datetime_immutable']);

        $form->submit('08:10');

        self::assertFalse($form->isSynchronized());
    }

    public function testTransformerRejectsInvalidTimes(): void
    {
        $this->expectException(TransformationFailedException::class);

        new QuarterHourTransformer()->reverseTransform('midi');
    }
}

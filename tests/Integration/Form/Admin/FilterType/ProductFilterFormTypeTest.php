<?php

declare(strict_types=1);

namespace App\Tests\Integration\Form\Admin\FilterType;

use App\Form\Admin\FilterType\ProductFilterFormType;
use App\Form\DTO\ProductFilterModel;
use DateTimeInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

#[Group(name: 'integration')]
class ProductFilterFormTypeTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->formFactory = self::getContainer()->get('form.factory');
    }

    public function testEmptySubmitMapsCompleteRangeShapes(): void
    {
        $form = $this->formFactory->create(ProductFilterFormType::class, new ProductFilterModel());
        $form->submit([]);

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isSynchronized());
        self::assertInstanceOf(ProductFilterModel::class, $form->getData());

        $model = $form->getData();
        self::assertSame(['left_number' => null, 'right_number' => null], $model->price);
        self::assertSame(['left_number' => null, 'right_number' => null], $model->quantity);
        self::assertSame(['left_datetime' => null, 'right_datetime' => null], $model->createdAt);
        self::assertNull($model->isPublished);
    }

    #[DataProvider(methodName: 'provideNumberRanges')]
    public function testNumberRangeMapping(string $field, array $submitted, array $expected): void
    {
        $form = $this->formFactory->create(ProductFilterFormType::class, new ProductFilterModel());
        $form->submit([$field => $submitted], false);

        self::assertTrue($form->isSynchronized());
        self::assertEquals($expected, $form->getData()->{$field});
    }

    public static function provideNumberRanges(): Generator
    {
        foreach (['price', 'quantity'] as $field) {
            yield $field.' lower' => [$field, ['left_number' => '10', 'right_number' => ''], ['left_number' => 10, 'right_number' => null]];
            yield $field.' upper' => [$field, ['left_number' => '', 'right_number' => '20'], ['left_number' => null, 'right_number' => 20]];
            yield $field.' both' => [$field, ['left_number' => '10', 'right_number' => '20'], ['left_number' => 10, 'right_number' => 20]];
        }
    }

    #[DataProvider(methodName: 'provideDateTimeRanges')]
    public function testDateTimeRangeMapping(array $submitted, ?string $expectedLeft, ?string $expectedRight): void
    {
        $form = $this->formFactory->create(ProductFilterFormType::class, new ProductFilterModel());
        $form->submit(['createdAt' => $submitted], false);

        self::assertTrue($form->isSynchronized());
        $range = $form->getData()->createdAt;
        self::assertDateTimeValue($expectedLeft, $range['left_datetime']);
        self::assertDateTimeValue($expectedRight, $range['right_datetime']);
    }

    public static function provideDateTimeRanges(): Generator
    {
        yield 'lower' => [['left_datetime' => '2024-01-02T03:04', 'right_datetime' => ''], '2024-01-02 03:04', null];
        yield 'upper' => [['left_datetime' => '', 'right_datetime' => '2024-02-03T04:05'], null, '2024-02-03 04:05'];
        yield 'both' => [['left_datetime' => '2024-01-02T03:04', 'right_datetime' => '2024-02-03T04:05'], '2024-01-02 03:04', '2024-02-03 04:05'];
    }

    #[DataProvider(methodName: 'provideBooleanValues')]
    public function testBooleanMapping(string $submitted, ?string $expected): void
    {
        $form = $this->formFactory->create(ProductFilterFormType::class, new ProductFilterModel());
        $form->submit(['isPublished' => $submitted], false);

        self::assertTrue($form->isSynchronized());
        self::assertSame($expected, $form->getData()->isPublished);
    }

    public static function provideBooleanValues(): Generator
    {
        yield 'yes' => ['y', 'y'];
        yield 'no' => ['n', 'n'];
        yield 'empty' => ['', null];
    }

    public function testScalarMapping(): void
    {
        $form = $this->formFactory->create(ProductFilterFormType::class, new ProductFilterModel());
        $form->submit(['id' => '42', 'title' => 'phone'], false);

        self::assertTrue($form->isSynchronized());
        self::assertEquals(42, $form->getData()->id);
        self::assertSame('phone', $form->getData()->title);
    }

    private static function assertDateTimeValue(?string $expected, ?DateTimeInterface $actual): void
    {
        if (null === $expected) {
            self::assertNull($actual);

            return;
        }

        self::assertInstanceOf(DateTimeInterface::class, $actual);
        self::assertSame($expected, $actual->format('Y-m-d H:i'));
    }
}

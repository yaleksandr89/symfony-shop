<?php

declare(strict_types=1);

namespace App\Tests\Integration\Form\Admin\FilterType;

use App\Form\Admin\FilterType\ProductFilterFormType;
use App\Form\DTO\ProductFilterModel;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

#[Group(name: 'integration')]
class ProductFilterFormTypeTest extends KernelTestCase
{
    private const DATE_RANGE_ERROR_KEY = 'admin.filter.date_range.invalid_order';

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
        self::assertSame(['left_date' => null, 'right_date' => null], $model->createdAt);
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

    #[DataProvider(methodName: 'provideDateRangeValidation')]
    public function testDateRangeValidation(array $submitted, bool $expectedValid): void
    {
        $form = $this->formFactory->create(
            ProductFilterFormType::class,
            new ProductFilterModel(),
            ['csrf_protection' => false],
        );
        $form->submit(['createdAt' => $submitted], false);

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isSynchronized());
        self::assertSame(['filtering'], $form->getConfig()->getOption('validation_groups'));
        self::assertSame($expectedValid, $form->isValid());

        $errors = $form->get('createdAt')->getErrors();
        if ($expectedValid) {
            self::assertCount(0, $errors);

            return;
        }

        self::assertCount(1, $errors);
        self::assertSame(self::DATE_RANGE_ERROR_KEY, $errors[0]->getMessageTemplate());
    }

    public static function provideDateRangeValidation(): Generator
    {
        yield 'reversed' => [['left_date' => '2026-07-15', 'right_date' => '2026-07-01'], false];
        yield 'same day' => [['left_date' => '2026-07-15', 'right_date' => '2026-07-15'], true];
        yield 'ascending' => [['left_date' => '2026-07-01', 'right_date' => '2026-07-15'], true];
        yield 'lower only' => [['left_date' => '2026-07-15', 'right_date' => ''], true];
        yield 'upper only' => [['left_date' => '', 'right_date' => '2026-07-15'], true];
        yield 'empty' => [['left_date' => '', 'right_date' => ''], true];
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

}

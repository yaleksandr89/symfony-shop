<?php

declare(strict_types=1);

namespace App\Tests\Integration\Form\Admin\FilterType;

use App\Form\Admin\FilterType\OrderFilterFormType;
use App\Form\DTO\OrderFilterModel;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

#[Group(name: 'integration')]
class OrderFilterFormTypeTest extends KernelTestCase
{
    private const DATE_RANGE_ERROR_KEY = 'admin.filter.date_range.invalid_order';
    private const DATE_RANGE_ERROR = 'Дата «От» не может быть позднее даты «До».';

    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->formFactory = self::getContainer()->get('form.factory');
    }

    public function testEmptySubmitMapsCompleteRangeShapes(): void
    {
        $form = $this->formFactory->create(OrderFilterFormType::class, new OrderFilterModel());
        $form->submit([]);

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isSynchronized());
        self::assertInstanceOf(OrderFilterModel::class, $form->getData());

        $model = $form->getData();
        self::assertSame(['left_number' => null, 'right_number' => null], $model->totalPrice);
        self::assertSame(['left_date' => null, 'right_date' => null], $model->createdAt);
    }

    #[DataProvider(methodName: 'provideNumberRanges')]
    public function testTotalPriceMapping(array $submitted, array $expected): void
    {
        $form = $this->formFactory->create(OrderFilterFormType::class, new OrderFilterModel());
        $form->submit(['totalPrice' => $submitted], false);

        self::assertTrue($form->isSynchronized());
        self::assertEquals($expected, $form->getData()->totalPrice);
    }

    public static function provideNumberRanges(): Generator
    {
        yield 'lower' => [['left_number' => '10', 'right_number' => ''], ['left_number' => 10, 'right_number' => null]];
        yield 'upper' => [['left_number' => '', 'right_number' => '20'], ['left_number' => null, 'right_number' => 20]];
        yield 'both' => [['left_number' => '10', 'right_number' => '20'], ['left_number' => 10, 'right_number' => 20]];
    }

    #[DataProvider(methodName: 'provideDateRangeValidation')]
    public function testDateRangeValidation(array $submitted, bool $expectedValid): void
    {
        $form = $this->formFactory->create(
            OrderFilterFormType::class,
            new OrderFilterModel(),
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
        self::assertSame(self::DATE_RANGE_ERROR, $errors[0]->getMessage());
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

    public function testScalarMapping(): void
    {
        $form = $this->formFactory->create(OrderFilterFormType::class, new OrderFilterModel());
        $form->submit(['id' => '42', 'status' => '1'], false);

        self::assertTrue($form->isSynchronized());
        self::assertEquals(42, $form->getData()->id);
        self::assertSame(1, $form->getData()->status);
    }

}

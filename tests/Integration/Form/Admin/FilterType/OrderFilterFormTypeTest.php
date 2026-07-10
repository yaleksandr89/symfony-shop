<?php

declare(strict_types=1);

namespace App\Tests\Integration\Form\Admin\FilterType;

use App\Form\Admin\FilterType\OrderFilterFormType;
use App\Form\DTO\OrderFilterModel;
use DateTimeInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

#[Group(name: 'integration')]
class OrderFilterFormTypeTest extends KernelTestCase
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
        $form = $this->formFactory->create(OrderFilterFormType::class, new OrderFilterModel());
        $form->submit([]);

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isSynchronized());
        self::assertInstanceOf(OrderFilterModel::class, $form->getData());

        $model = $form->getData();
        self::assertSame(['left_number' => null, 'right_number' => null], $model->totalPrice);
        self::assertSame(['left_datetime' => null, 'right_datetime' => null], $model->createdAt);
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

    #[DataProvider(methodName: 'provideDateTimeRanges')]
    public function testDateTimeRangeMapping(array $submitted, ?string $expectedLeft, ?string $expectedRight): void
    {
        $form = $this->formFactory->create(OrderFilterFormType::class, new OrderFilterModel());
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

    public function testScalarMapping(): void
    {
        $form = $this->formFactory->create(OrderFilterFormType::class, new OrderFilterModel());
        $form->submit(['id' => '42', 'status' => '1'], false);

        self::assertTrue($form->isSynchronized());
        self::assertEquals(42, $form->getData()->id);
        self::assertSame(1, $form->getData()->status);
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

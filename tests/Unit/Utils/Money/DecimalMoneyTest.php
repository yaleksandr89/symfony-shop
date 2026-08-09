<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils\Money;

use App\Utils\Money\DecimalMoney;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Group(name: 'unit')]
class DecimalMoneyTest extends TestCase
{
    #[TestWith(['89.99', 3, 26997, '269.97'])]
    #[TestWith(['0.10', 3, 30, '0.30'])]
    #[TestWith(['19.99', 7, 13993, '139.93'])]
    #[TestWith(['90.60', 1, 9060, '90.60'])]
    #[TestWith(['0.00', 0, 0, '0.00'])]
    public function testMultipliesAndFormatsCanonicalAmounts(string $amount, int $quantity, int $expectedCents, string $expectedAmount): void
    {
        $cents = DecimalMoney::multiplyToCents($amount, $quantity);

        self::assertSame($expectedCents, $cents);
        self::assertSame($expectedAmount, DecimalMoney::fromCents($cents));
    }

    public function testAddsLineTotalsInCents(): void
    {
        $totalCents = DecimalMoney::addCents(
            DecimalMoney::toCents('94.90'),
            DecimalMoney::multiplyToCents('89.99', 3)
        );

        self::assertSame(36487, $totalCents);
        self::assertSame('364.87', DecimalMoney::fromCents($totalCents));
    }

    #[TestWith(['94', 9400, '94.00'])]
    #[TestWith(['94.9', 9490, '94.90'])]
    #[TestWith(['94.90', 9490, '94.90'])]
    #[TestWith(['0', 0, '0.00'])]
    #[TestWith(['0.1', 10, '0.10'])]
    #[TestWith(['0.10', 10, '0.10'])]
    public function testNormalizesEquivalentFixedPointAmounts(string $amount, int $expectedCents, string $expectedAmount): void
    {
        self::assertSame($expectedCents, DecimalMoney::toCents($amount));
        self::assertSame($expectedAmount, DecimalMoney::fromCents(DecimalMoney::toCents($amount)));
        self::assertSame($expectedAmount, DecimalMoney::normalize($amount));
    }

    #[TestWith(['089.99'])]
    #[TestWith(['-89.99'])]
    #[TestWith(['+89.99'])]
    #[TestWith(['89.999'])]
    #[TestWith(['89,99'])]
    #[TestWith(['1e2'])]
    #[TestWith([''])]
    #[TestWith(['not-a-price'])]
    public function testRejectsInvalidDecimalAmounts(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        DecimalMoney::toCents($amount);
    }

    public function testRejectsNegativeQuantity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DecimalMoney::multiplyToCents('1.00', -1);
    }

    public function testRejectsAmountsAndLineTotalsOutsideTheIntegerRange(): void
    {
        try {
            DecimalMoney::toCents('92233720368547758.08');
            self::fail('Expected the amount overflow to be rejected.');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);

        DecimalMoney::multiplyToCents('92233720368547758.07', 2);
    }

    #[TestDox('Отрицательное количество центов отклоняется при форматировании')]
    public function testFromCentsRejectsNegativeValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DecimalMoney::fromCents(-1);
    }

    #[TestDox('Сложение отклоняет отрицательные операнды')]
    #[TestWith([-1, 0])]
    #[TestWith([0, -1])]
    public function testAddCentsRejectsNegativeOperands(int $totalCents, int $lineCents): void
    {
        $this->expectException(InvalidArgumentException::class);

        DecimalMoney::addCents($totalCents, $lineCents);
    }

    #[TestDox('Переполнение при сложении центов отклоняется без оборачивания')]
    public function testAddCentsRejectsIntegerOverflow(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DecimalMoney::addCents(PHP_INT_MAX, 1);
    }
}

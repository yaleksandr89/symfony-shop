<?php

declare(strict_types=1);

namespace App\Utils\Money;

use InvalidArgumentException;

final class DecimalMoney
{
    public static function toCents(string $amount): int
    {
        if (1 !== preg_match('/^(0|[1-9]\d*)(?:\.(\d{1,2}))?$/D', $amount, $matches)) {
            throw new InvalidArgumentException('Amount must be a non-negative decimal string with no more than two fractional digits.');
        }

        return self::parseDigits($matches[1].str_pad($matches[2] ?? '', 2, '0'));
    }

    public static function multiplyToCents(string $amount, int $quantity): int
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Quantity must be a non-negative integer.');
        }

        $cents = self::toCents($amount);

        if (0 !== $quantity && $cents > intdiv(PHP_INT_MAX, $quantity)) {
            throw new InvalidArgumentException('Line total exceeds the integer range.');
        }

        return $cents * $quantity;
    }

    public static function addCents(int $totalCents, int $lineCents): int
    {
        self::assertNonNegativeCents($totalCents);
        self::assertNonNegativeCents($lineCents);

        if ($totalCents > PHP_INT_MAX - $lineCents) {
            throw new InvalidArgumentException('Total exceeds the integer range.');
        }

        return $totalCents + $lineCents;
    }

    public static function fromCents(int $cents): string
    {
        self::assertNonNegativeCents($cents);

        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function normalize(string $amount): string
    {
        return self::fromCents(self::toCents($amount));
    }

    private static function parseDigits(string $digits): int
    {
        $value = 0;

        foreach (str_split($digits) as $digit) {
            $digitValue = ord($digit) - ord('0');

            if ($value > intdiv(PHP_INT_MAX - $digitValue, 10)) {
                throw new InvalidArgumentException('Amount exceeds the integer range.');
            }

            $value = $value * 10 + $digitValue;
        }

        return $value;
    }

    private static function assertNonNegativeCents(int $cents): void
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('Cents must be a non-negative integer.');
        }
    }
}

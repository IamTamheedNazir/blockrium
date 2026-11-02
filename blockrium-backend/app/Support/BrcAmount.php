<?php

namespace App\Support;

use InvalidArgumentException;

final class BrcAmount
{
    public const DECIMALS = 8;
    public const SCALE = 100000000;

    public static function toUnits($amount): int
    {
        if (is_float($amount)) {
            if (!is_finite($amount) || $amount < 0) {
                throw new InvalidArgumentException('BRC amount must be a finite, non-negative decimal.');
            }
            $amount = number_format($amount, self::DECIMALS, '.', '');
        }

        $amount = trim((string) $amount);
        if (!preg_match('/^(?:0|[1-9]\d*)(?:\.(\d{1,8}))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('BRC amount must have no more than eight decimal places.');
        }

        $parts = explode('.', $amount, 2);
        $whole = (int) $parts[0];
        $fraction = isset($parts[1]) ? (int) str_pad($parts[1], self::DECIMALS, '0') : 0;
        if ($whole > intdiv(PHP_INT_MAX - $fraction, self::SCALE)) {
            throw new InvalidArgumentException('BRC amount exceeds the supported range.');
        }

        return ($whole * self::SCALE) + $fraction;
    }

    public static function fromUnits(int $units): string
    {
        if ($units < 0) {
            throw new InvalidArgumentException('BRC balance cannot be negative.');
        }

        return intdiv($units, self::SCALE) . '.' . str_pad((string) ($units % self::SCALE), self::DECIMALS, '0', STR_PAD_LEFT);
    }
}

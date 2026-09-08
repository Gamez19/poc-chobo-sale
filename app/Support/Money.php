<?php

namespace App\Support;

final class Money
{
    private function __construct() {}

    public static function fromDecimal(int|float|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public static function format(?int $cents): string
    {
        return 'C$ '.number_format(($cents ?? 0) / 100, 2, '.', ',');
    }
}

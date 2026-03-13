<?php

namespace App\Support;

class MoneyFormatter
{
    public static function format(int|float|string $amount, string $currency = 'USD'): string
    {
        return sprintf('%s %s', strtoupper($currency), number_format((float) $amount, 2));
    }
}

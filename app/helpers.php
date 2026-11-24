<?php

if (! function_exists('rupiah')) {
    function rupiah(int|float|string|null $amount): string
    {
        return 'Rp'.number_format((float) $amount, 0, ',', '.');
    }
}

if (! function_exists('qty')) {
    /** 3.50 -> "3,5", 2.00 -> "2" */
    function qty(int|float|string|null $value): string
    {
        $formatted = number_format((float) $value, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}

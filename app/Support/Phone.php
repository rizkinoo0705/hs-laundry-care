<?php

namespace App\Support;

class Phone
{
    /** Simpan nomor dalam format 08xxxxxxxxxx (hanya angka). */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    /** Format internasional untuk wa.me (62xxxxxxxxxx). */
    public static function toWa(?string $phone): string
    {
        $digits = self::normalize($phone) ?? '';

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }

    public static function waLink(?string $phone, string $message = ''): string
    {
        $url = 'https://wa.me/'.self::toWa($phone);

        return $message === '' ? $url : $url.'?text='.rawurlencode($message);
    }

    /** Tampilan 0813-1314-8938. */
    public static function format(?string $phone): string
    {
        $digits = self::normalize($phone) ?? '';

        if (strlen($digits) < 10) {
            return $digits;
        }

        return substr($digits, 0, 4).'-'.substr($digits, 4, 4).'-'.substr($digits, 8);
    }
}

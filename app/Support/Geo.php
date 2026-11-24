<?php

namespace App\Support;

class Geo
{
    /** Jarak garis lurus (km) antara dua titik. */
    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Link navigasi Google Maps (gratis, terbuka di aplikasi Maps HP kurir). */
    public static function directionsUrl(?float $lat, ?float $lng, ?string $address = null): ?string
    {
        if ($lat !== null && $lng !== null) {
            return 'https://www.google.com/maps/dir/?api=1&destination='.$lat.','.$lng;
        }

        if ($address) {
            return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address);
        }

        return null;
    }
}

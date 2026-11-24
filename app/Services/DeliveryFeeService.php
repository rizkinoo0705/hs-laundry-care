<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Setting;

/**
 * Menghitung ongkir antar-jemput:
 *  jarak pelanggan -> zona -> tarif per perjalanan x jumlah perjalanan -> potongan promo.
 */
class DeliveryFeeService
{
    /**
     * @param  int  $trips  0, 1 (jemput ATAU antar), atau 2 (jemput DAN antar)
     * @param  int  $laundryTotal  total cucian setelah diskon (untuk syarat minimal belanja)
     * @param  int|null  $manualFee  ongkir manual (total, sebelum promo) bila diisi kasir
     */
    public function quote(?Customer $customer, int $trips, int $laundryTotal, ?int $manualFee = null): array
    {
        $result = [
            'trips' => $trips,
            'distance_km' => null,
            'distance_source' => null,
            'zone_id' => null,
            'zone_name' => null,
            'fee_per_trip' => 0,
            'fee' => 0,
            'discount' => 0,
            'total' => 0,
            'note' => null,
            'out_of_zone' => false,
            'no_location' => false,
            'manual' => false,
        ];

        if ($trips <= 0) {
            return $result;
        }

        $distance = $customer ? $this->distanceFor($customer) : null;
        $result['distance_km'] = $distance;
        $result['distance_source'] = $customer?->distance_source;

        if ($manualFee !== null) {
            $result['fee'] = max(0, $manualFee);
            $result['manual'] = true;
        } elseif ($distance === null) {
            $result['no_location'] = true;
        } else {
            $zone = DeliveryZone::forDistance($distance);
            if ($zone) {
                $result['zone_id'] = $zone->id;
                $result['zone_name'] = $zone->name;
                $result['fee_per_trip'] = $zone->fee;
                $result['fee'] = $zone->fee * $trips;
            } else {
                $result['out_of_zone'] = true;
            }
        }

        [$discount, $note] = $this->bestPromo($customer, $result['fee'], $laundryTotal);
        $result['discount'] = $discount;
        $result['note'] = $note;
        $result['total'] = $result['fee'] - $discount;

        return $result;
    }

    public function distanceFor(Customer $customer): ?float
    {
        if ($customer->distance_km !== null) {
            return (float) $customer->distance_km;
        }

        return $customer->straightLineKm();
    }

    /** Ambil potongan terbesar dari promo yang berlaku (tidak ditumpuk). */
    private function bestPromo(?Customer $customer, int $fee, int $laundryTotal): array
    {
        if ($fee <= 0) {
            return [0, null];
        }

        $candidates = [];

        if ($customer && $customer->hasActiveDeliveryPromo()) {
            if ($customer->delivery_promo === 'free') {
                $candidates[] = [$fee, 'Gratis ongkir (pelanggan khusus)'];
            } elseif ($customer->delivery_promo === 'percent') {
                $pct = min(100, $customer->delivery_promo_value);
                $candidates[] = [(int) round($fee * $pct / 100), 'Diskon ongkir '.$pct.'% (pelanggan khusus)'];
            }
        }

        $minTotal = (int) Setting::get('free_delivery_min_total', 0);
        if ($minTotal > 0 && $laundryTotal >= $minTotal) {
            $candidates[] = [$fee, 'Gratis ongkir (belanja min. '.rupiah($minTotal).')'];
        }

        if ($customer && $customer->is_member && Setting::get('free_delivery_members') === '1') {
            $candidates[] = [$fee, 'Gratis ongkir member'];
        }

        if (! $candidates) {
            return [0, null];
        }

        usort($candidates, fn ($a, $b) => $b[0] <=> $a[0]);

        return [min($fee, $candidates[0][0]), $candidates[0][1]];
    }
}

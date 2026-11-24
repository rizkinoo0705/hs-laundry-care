<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Setting;
use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliverySettingsController extends Controller
{
    public function edit()
    {
        return view('settings.delivery', [
            'outlet' => Setting::outletLocation(),
            'zones' => DeliveryZone::orderBy('max_km')->get(),
            'freeMinTotal' => (int) Setting::get('free_delivery_min_total', 0),
            'freeMembers' => Setting::get('free_delivery_members') === '1',
            'promoCustomers' => Customer::where('delivery_promo', '!=', 'none')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'outlet_lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:outlet_lng'],
            'outlet_lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:outlet_lat'],
            'free_delivery_min_total' => ['nullable', 'integer', 'min:0'],
            'free_delivery_members' => ['nullable', 'boolean'],
            'zones' => ['nullable', 'array', 'max:20'],
            'zones.*.id' => ['nullable', 'integer'],
            'zones.*.name' => ['nullable', 'string', 'max:50'],
            'zones.*.max_km' => ['nullable', 'numeric', 'min:0.1', 'max:200'],
            'zones.*.fee' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'zones.*.active' => ['nullable', 'boolean'],
            'zones.*.delete' => ['nullable', 'boolean'],
        ], [
            'zones.*.max_km.min' => 'Jarak maksimal zona minimal 0,1 km.',
        ]);

        $oldOutlet = Setting::outletLocation();

        DB::transaction(function () use ($data, $request, $oldOutlet) {
            Setting::set('outlet_lat', $data['outlet_lat'] ?? null);
            Setting::set('outlet_lng', $data['outlet_lng'] ?? null);
            Setting::set('free_delivery_min_total', (int) ($data['free_delivery_min_total'] ?? 0));
            Setting::set('free_delivery_members', $request->boolean('free_delivery_members') ? '1' : '0');

            foreach ($data['zones'] ?? [] as $row) {
                $zone = ! empty($row['id']) ? DeliveryZone::find($row['id']) : null;

                if (! empty($row['delete'])) {
                    $zone?->delete();
                    continue;
                }

                // Baris kosong diabaikan.
                if (empty($row['name']) && empty($row['max_km']) && ! isset($row['fee'])) {
                    continue;
                }
                if (empty($row['max_km']) || ! isset($row['fee']) || $row['fee'] === '') {
                    continue;
                }

                $values = [
                    'name' => $row['name'] ?: 'Zona '.qty($row['max_km']).' km',
                    'max_km' => $row['max_km'],
                    'fee' => (int) $row['fee'],
                    'active' => ! empty($row['active']),
                ];

                $zone ? $zone->update($values) : DeliveryZone::create($values);
            }

            // Lokasi outlet berubah: hitung ulang jarak garis lurus semua pelanggan.
            $newOutlet = Setting::outletLocation();
            if ($newOutlet && $newOutlet !== $oldOutlet) {
                Customer::whereNotNull('latitude')->whereNotNull('longitude')->each(function (Customer $c) use ($newOutlet) {
                    $c->distance_km = round(Geo::haversineKm($newOutlet[0], $newOutlet[1], $c->latitude, $c->longitude), 2);
                    $c->distance_source = 'lurus';
                    $c->saveQuietly();
                });
            }
        });

        return redirect()->route('settings.delivery')->with('success', 'Pengaturan antar-jemput disimpan.');
    }
}

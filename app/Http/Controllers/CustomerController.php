<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Setting;
use App\Support\Geo;
use App\Services\StampService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()->withCount('orders')->orderBy('name');

        if ($search = trim((string) $request->query('q'))) {
            $digits = preg_replace('/\D+/', '', $search);
            $query->where(function ($q) use ($search, $digits) {
                $q->whereLike('name', "%{$search}%")
                    ->orWhereLike('member_code', "%{$search}%");
                if ($digits !== '') {
                    $q->orWhereLike('phone', "%{$digits}%");
                }
            });
        }

        if ($request->query('member') === '1') {
            $query->where('is_member', true);
        }

        return view('customers.index', [
            'customers' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('customers.form', ['customer' => new Customer, 'outlet' => Setting::outletLocation()]);
    }

    public function store(Request $request)
    {
        $customer = Customer::create($this->validated($request));

        return redirect()->route('customers.show', $customer)->with('success', 'Pelanggan ditambahkan.');
    }

    public function show(Customer $customer)
    {
        $distance = $customer->distance_km !== null ? (float) $customer->distance_km : $customer->straightLineKm();

        return view('customers.show', [
            'customer' => $customer,
            'distance' => $distance,
            'zone' => $distance !== null ? DeliveryZone::forDistance($distance) : null,
            'orders' => $customer->orders()->latest()->limit(20)->get(),
            'stampLogs' => $customer->stampTransactions()->latest()->limit(20)->get(),
        ]);
    }

    public function edit(Customer $customer)
    {
        return view('customers.form', ['customer' => $customer, 'outlet' => Setting::outletLocation()]);
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request, $customer));

        return redirect()->route('customers.show', $customer)->with('success', 'Data pelanggan disimpan.');
    }

    public function redeem(Request $request, Customer $customer, StampService $stamps)
    {
        $stamps->redeem($customer, $request->user());

        return back()->with('success', 'Hadiah ditukar. Stempel dikurangi '.config('laundry.stamp_target').'.');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'min:9', 'max:20', Rule::unique('customers', 'phone')->ignore($customer?->id)],
            'address' => ['nullable', 'string', 'max:500'],
            'is_member' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'road_distance_km' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'delivery_promo' => ['nullable', Rule::in(array_keys(Customer::DELIVERY_PROMOS))],
            'delivery_promo_value' => ['nullable', 'integer', 'min:0', 'max:100'],
            'delivery_promo_until' => ['nullable', 'date'],
        ], [
            'phone.unique' => 'Nomor HP ini sudah terdaftar.',
        ]);

        $data['is_member'] = $request->boolean('is_member');
        $data['delivery_promo'] = $data['delivery_promo'] ?? 'none';
        $data['delivery_promo_value'] = $data['delivery_promo'] === 'percent' ? (int) ($data['delivery_promo_value'] ?? 0) : 0;
        if ($data['delivery_promo'] === 'none') {
            $data['delivery_promo_until'] = null;
        }

        [$data['distance_km'], $data['distance_source']] = $this->distance($data);
        unset($data['road_distance_km']);

        return $data;
    }

    /**
     * Jarak dari outlet. Pakai jarak jalan dari peta bila masuk akal,
     * selain itu jarak garis lurus.
     */
    private function distance(array $data): array
    {
        $outlet = Setting::outletLocation();

        if (! $outlet || ! isset($data['latitude'], $data['longitude'])) {
            return [null, null];
        }

        $straight = Geo::haversineKm($outlet[0], $outlet[1], (float) $data['latitude'], (float) $data['longitude']);
        $road = isset($data['road_distance_km']) ? (float) $data['road_distance_km'] : null;

        if ($road !== null && $road >= $straight * 0.9 && $road <= $straight * 6 + 1) {
            return [round($road, 2), 'jalan'];
        }

        return [round($straight, 2), 'lurus'];
    }
}

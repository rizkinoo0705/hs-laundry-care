<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Mitra;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->date('date') ?? now();

        $query = Delivery::with('customer', 'mitra', 'order', 'courier')
            ->whereDate('scheduled_at', $date->toDateString())
            ->orderBy('scheduled_at');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return view('deliveries.index', [
            'deliveries' => $query->get(),
            'date' => $date,
            'couriers' => User::whereIn('role', ['admin', 'kasir'])->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $order = $request->query('order') ? Order::with('customer')->find($request->query('order')) : null;

        return view('deliveries.create', [
            'order' => $order,
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'phone', 'address']),
            'mitras' => Mitra::where('active', true)->orderBy('name')->get(),
            'couriers' => User::whereIn('role', ['admin', 'kasir'])->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Delivery::TYPES))],
            'target' => ['required', Rule::in(['customer', 'mitra'])],
            'customer_id' => ['nullable', 'required_if:target,customer', Rule::exists('customers', 'id')],
            'mitra_id' => ['nullable', 'required_if:target,mitra', Rule::exists('mitras', 'id')],
            'order_id' => ['nullable', Rule::exists('orders', 'id')],
            'address' => ['nullable', 'string', 'max:500'],
            'scheduled_at' => ['required', 'date'],
            'courier_id' => ['nullable', Rule::exists('users', 'id')],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'customer_id.required_if' => 'Pilih pelanggan.',
            'mitra_id.required_if' => 'Pilih mitra.',
        ]);

        if ($data['target'] === 'customer') {
            $data['mitra_id'] = null;
            $customer = Customer::find($data['customer_id']);
            $fallback = $customer?->address;
            if ($customer && $customer->hasLocation() && empty($data['address'])) {
                $data['latitude'] = $customer->latitude;
                $data['longitude'] = $customer->longitude;
            }
            $data['distance_km'] = $customer?->distance_km;
        } else {
            $data['customer_id'] = null;
            $fallback = Mitra::find($data['mitra_id'])?->address;
        }

        $data['address'] = $data['address'] ?: $fallback;

        if (! $data['address']) {
            return back()->withInput()->withErrors(['address' => 'Alamat wajib diisi (belum ada alamat tersimpan).']);
        }

        unset($data['target']);
        $data['status'] = 'terjadwal';

        $delivery = Delivery::create($data);

        return redirect()->route('deliveries.index', ['date' => $delivery->scheduled_at->toDateString()])
            ->with('success', 'Jadwal '.strtolower($delivery->typeLabel()).' dibuat.');
    }

    public function updateStatus(Request $request, Delivery $delivery)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Delivery::STATUSES))],
            'courier_id' => ['nullable', Rule::exists('users', 'id')],
        ]);

        $delivery->status = $data['status'];
        if (array_key_exists('courier_id', $data) && $data['courier_id']) {
            $delivery->courier_id = $data['courier_id'];
        }
        $delivery->completed_at = $data['status'] === 'selesai' ? now() : null;
        $delivery->save();

        return back()->with('success', 'Status antar-jemput diperbarui.');
    }
}

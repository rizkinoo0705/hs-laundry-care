<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Mitra;
use App\Models\Order;
use App\Models\Staff;
use App\Services\StaffCommissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->date('date') ?? now();

        $query = Delivery::with('customer', 'mitra', 'order', 'staff')
            ->whereDate('scheduled_at', $date->toDateString())
            ->orderBy('scheduled_at');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return view('deliveries.index', [
            'deliveries' => $query->get(),
            'date' => $date,
            'couriers' => Staff::active()->get(),
        ]);
    }

    public function create(Request $request)
    {
        $order = $request->query('order') ? Order::with('customer')->find($request->query('order')) : null;

        return view('deliveries.create', [
            'order' => $order,
            'orders' => Order::with('customer')->where('status', '!=', 'batal')
                ->where(fn ($q) => $q->whereNotIn('status', ['selesai'])->orWhere('id', $order?->id))
                ->latest()->limit(300)->get(),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'phone', 'address']),
            'mitras' => Mitra::where('active', true)->orderBy('name')->get(),
            'couriers' => Staff::active()->get(),
        ]);
    }

    public function store(Request $request)
    {
        // Bila order dipilih untuk pelanggan, isi pelanggan dari order (sebelum validasi).
        if ($request->filled('order_id') && $request->input('target', 'customer') === 'customer' && ! $request->filled('customer_id')) {
            $request->merge(['customer_id' => Order::whereKey($request->input('order_id'))->value('customer_id')]);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Delivery::TYPES))],
            'target' => ['required', Rule::in(['customer', 'mitra'])],
            'customer_id' => ['nullable', 'required_if:target,customer', Rule::exists('customers', 'id')],
            'mitra_id' => ['nullable', 'required_if:target,mitra', Rule::exists('mitras', 'id')],
            'order_id' => ['nullable', 'required_if:type,antar', Rule::exists('orders', 'id')],
            'address' => ['nullable', 'string', 'max:500'],
            'scheduled_at' => ['required', 'date'],
            'staff_id' => ['nullable', Rule::exists('staff', 'id')],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'customer_id.required_if' => 'Pilih pelanggan.',
            'order_id.required_if' => 'Jadwal antar wajib memilih nomor order.',
            'mitra_id.required_if' => 'Pilih mitra.',
        ]);

        // Antar ke pelanggan: pelanggan selalu mengikuti order yang dipilih.
        if (! empty($data['order_id']) && $data['target'] === 'customer') {
            $data['customer_id'] = Order::find($data['order_id'])->customer_id;
        }

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

        $data['address'] = ($data['address'] ?? null) ?: $fallback;

        if (! $data['address']) {
            return back()->withInput()->withErrors(['address' => 'Alamat wajib diisi (belum ada alamat tersimpan).']);
        }

        unset($data['target']);
        $data['status'] = 'terjadwal';

        $delivery = Delivery::create($data);

        return redirect()->route('deliveries.index', ['date' => $delivery->scheduled_at->toDateString()])
            ->with('success', 'Jadwal '.strtolower($delivery->typeLabel()).' dibuat.');
    }

    public function updateStatus(Request $request, Delivery $delivery, StaffCommissionService $commissions)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Delivery::STATUSES))],
            'staff_id' => ['nullable', Rule::exists('staff', 'id')],
        ]);

        if ($data['status'] === 'selesai' && empty($data['staff_id']) && ! $delivery->staff_id
            && StaffCommissionService::tripRate($delivery->type) > 0) {
            return back()->withErrors(['staff_id' => 'Pilih kurir sebelum menandai selesai, agar komisinya tercatat.']);
        }

        $delivery->status = $data['status'];
        if (! empty($data['staff_id'])) {
            $delivery->staff_id = $data['staff_id'];
        }
        $delivery->completed_at = $data['status'] === 'selesai' ? ($delivery->completed_at ?? now()) : null;
        $delivery->save();

        $commissions->syncForDelivery($delivery);

        return back()->with('success', 'Status antar-jemput diperbarui.');
    }
}

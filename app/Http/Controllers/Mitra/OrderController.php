<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Service;
use App\Services\OrderService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Mitra dropbox menimbang & menginput titipan pelanggan sendiri. */
class OrderController extends Controller
{
    public function __construct(private OrderService $orders)
    {
    }

    public function create(Request $request)
    {
        $this->mitraOf($request);

        return view('mitra.order-create', [
            'services' => Service::active()->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $mitra = $this->mitraOf($request);
        $request->merge(['customer_phone' => Phone::normalize($request->input('customer_phone'))]);

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'regex:/^0[0-9]{8,14}$/'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['required', Rule::exists('services', 'id')->where('active', true)],
            'items.*.qty' => ['required', 'numeric', 'min:0.1', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'customer_phone.regex' => 'Nomor HP tidak valid.',
            'items.required' => 'Tambahkan minimal satu layanan.',
        ]);

        // Mitra tidak menentukan ongkir, diskon, pembayaran, atau petugas.
        $order = $this->orders->create([
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'mitra_id' => $mitra->id,
            'delivery_method' => 'ambil',
            'items' => $data['items'],
            'notes' => $data['notes'] ?? null,
        ], $request->user());

        return redirect()->route('mitra.portal')
            ->with('success', 'Titipan '.$order->code.' tersimpan. Total '.rupiah($order->total).'.')
            ->with('printOrder', $order->id);
    }

    public function receipt(Request $request, Order $order)
    {
        abort_unless($order->mitra_id === $this->mitraOf($request)->id, 404);

        $order->load('items', 'customer', 'mitra', 'cashier');

        return view('orders.receipt', compact('order'));
    }

    private function mitraOf(Request $request)
    {
        $mitra = $request->user()->mitra;
        abort_unless($mitra && $mitra->active, 403, 'Akun ini belum terhubung ke mitra aktif. Hubungi admin.');

        return $mitra;
    }
}

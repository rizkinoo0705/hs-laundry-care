<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Mitra;
use App\Models\Order;
use App\Models\Service;
use App\Services\OrderService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders)
    {
    }

    public function index(Request $request)
    {
        $query = Order::with('customer', 'mitra')->latest();

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->whereLike('code', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->whereLike('name', "%{$search}%")
                        ->orWhereLike('phone', '%'.(preg_replace('/\D+/', '', $search) ?: $search).'%'));
            });
        }

        if ($status = $request->query('status')) {
            $status === 'proses' ? $query->inProgress() : $query->where('status', $status);
        }

        if ($payment = $request->query('payment')) {
            $query->where('payment_status', $payment);
        }

        if ($mitra = $request->query('mitra')) {
            $query->where('mitra_id', $mitra);
        }

        return view('orders.index', [
            'orders' => $query->paginate(20)->withQueryString(),
            'mitras' => Mitra::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('orders.create', [
            'services' => Service::active()->orderBy('category')->orderBy('name')->get(),
            'mitras' => Mitra::where('active', true)->orderBy('name')->get(),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'phone', 'address']),
            'selectedCustomer' => $request->query('customer') ? Customer::find($request->query('customer')) : null,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['customer_phone' => Phone::normalize($request->input('customer_phone'))]);

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'regex:/^0[0-9]{8,14}$/'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'mitra_id' => ['nullable', Rule::exists('mitras', 'id')],
            'delivery_method' => ['required', Rule::in(array_keys(Order::DELIVERY_METHODS))],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['required', Rule::exists('services', 'id')],
            'items.*.qty' => ['required', 'numeric', 'min:0.1', 'max:1000'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'pay_now' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', Rule::in(array_keys(Order::PAYMENT_METHODS))],
        ], [
            'customer_phone.regex' => 'Nomor HP tidak valid.',
            'items.required' => 'Tambahkan minimal satu layanan.',
        ]);

        $order = $this->orders->create($data, $request->user());

        return redirect()->route('orders.show', $order)->with('success', 'Order '.$order->code.' berhasil dibuat.');
    }

    public function show(Order $order)
    {
        $order->load('items', 'customer', 'mitra', 'cashier', 'payout');

        return view('orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::STATUSES))],
        ]);

        $this->orders->changeStatus($order, $data['status'], $request->user());

        return back()->with('success', 'Status order diubah ke "'.$order->statusLabel().'".');
    }

    public function pay(Request $request, Order $order)
    {
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(array_keys(Order::PAYMENT_METHODS))],
        ]);

        $this->orders->markPaid($order, $data['payment_method'], $request->user());

        return back()->with('success', 'Order '.$order->code.' sudah lunas.');
    }

    public function receipt(Order $order)
    {
        $order->load('items', 'customer', 'mitra', 'cashier');

        return view('orders.receipt', compact('order'));
    }
}

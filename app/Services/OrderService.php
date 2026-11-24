<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Mitra;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private StampService $stamps)
    {
    }

    /**
     * Buat order baru.
     *
     * $data: customer_name, customer_phone, customer_address?, mitra_id?, delivery_method,
     *        items: [[service_id, qty], ...], discount?, notes?, pay_now?, payment_method?
     */
    public function create(array $data, ?User $user = null): Order
    {
        return DB::transaction(function () use ($data, $user) {
            $customer = $this->resolveCustomer($data);

            $serviceIds = collect($data['items'])->pluck('service_id')->unique();
            $services = Service::whereIn('id', $serviceIds)->get()->keyBy('id');

            $items = [];
            $subtotal = 0;
            $totalKg = 0.0;     // berat sebenarnya
            $billedKg = 0.0;    // berat yang ditagih (dasar komisi mitra)
            $maxHours = 0;

            foreach ($data['items'] as $row) {
                $service = $services->get($row['service_id']);
                if (! $service || ! $service->active) {
                    throw ValidationException::withMessages(['items' => 'Layanan tidak ditemukan atau tidak aktif.']);
                }

                $qty = round((float) $row['qty'], 2);
                $charged = round($service->chargedQty($qty), 2);
                $lineTotal = (int) round($charged * $service->price);

                $items[] = [
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'unit' => $service->unit,
                    'qty' => $qty,
                    'charged_qty' => $charged,
                    'price' => $service->price,
                    'subtotal' => $lineTotal,
                ];

                $subtotal += $lineTotal;
                if ($service->unit === 'kg') {
                    $totalKg += $qty;
                    $billedKg += $charged;
                }
                $maxHours = max($maxHours, $service->duration_hours);
            }

            $discount = min((int) ($data['discount'] ?? 0), $subtotal);
            $total = $subtotal - $discount;

            $mitra = ! empty($data['mitra_id']) ? Mitra::find($data['mitra_id']) : null;
            $commission = $mitra ? $mitra->commissionFor($billedKg, $total) : 0;

            $payNow = ! empty($data['pay_now']);

            $order = Order::create([
                'code' => $this->nextCode(),
                'customer_id' => $customer->id,
                'mitra_id' => $mitra?->id,
                'user_id' => $user?->id,
                'status' => 'diterima',
                'delivery_method' => $data['delivery_method'] ?? 'ambil',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'total_kg' => $totalKg,
                'payment_status' => $payNow ? 'lunas' : 'belum',
                'payment_method' => $payNow ? ($data['payment_method'] ?? 'tunai') : null,
                'paid_at' => $payNow ? now() : null,
                'due_at' => now()->addHours($maxHours ?: 48),
                'notes' => $data['notes'] ?? null,
                'commission_amount' => $commission,
            ]);

            $order->items()->createMany($items);

            return $order->load('items', 'customer', 'mitra');
        });
    }

    public function changeStatus(Order $order, string $status, ?User $user = null): Order
    {
        if (! in_array($status, $order->allowedNextStatuses(), true)) {
            throw ValidationException::withMessages([
                'status' => 'Status tidak bisa diubah dari "'.$order->statusLabel().'" ke "'.(Order::STATUSES[$status] ?? $status).'".',
            ]);
        }

        return DB::transaction(function () use ($order, $status, $user) {
            $order->status = $status;

            if ($status === 'selesai') {
                $order->completed_at = now();
            }

            $order->save();

            $this->maybeAwardStamp($order, $user);

            return $order;
        });
    }

    public function markPaid(Order $order, string $method, ?User $user = null): Order
    {
        if ($order->isPaid()) {
            return $order;
        }

        return DB::transaction(function () use ($order, $method, $user) {
            $order->update([
                'payment_status' => 'lunas',
                'payment_method' => $method,
                'paid_at' => now(),
            ]);

            $this->maybeAwardStamp($order, $user);

            return $order;
        });
    }

    /** Member dapat 1 stempel saat order selesai DAN lunas (sekali per order). */
    private function maybeAwardStamp(Order $order, ?User $user): void
    {
        $order->loadMissing('customer');

        if ($order->stamp_awarded
            || $order->status !== 'selesai'
            || ! $order->isPaid()
            || ! $order->customer->is_member
            || $order->total < config('laundry.stamp_min_total')) {
            return;
        }

        $this->stamps->add($order->customer, 1, 'Order '.$order->code, $order, $user);
        $order->stamp_awarded = true;
        $order->save();
    }

    private function resolveCustomer(array $data): Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::findOrFail($data['customer_id']);
        }

        $phone = Phone::normalize($data['customer_phone'] ?? '');
        $customer = Customer::where('phone', $phone)->first();

        if ($customer) {
            if (! empty($data['customer_address']) && ! $customer->address) {
                $customer->update(['address' => $data['customer_address']]);
            }

            return $customer;
        }

        return Customer::create([
            'name' => $data['customer_name'],
            'phone' => $phone,
            'address' => $data['customer_address'] ?? null,
        ]);
    }

    /** Format: HS241124-001 (nomor urut per hari). */
    private function nextCode(): string
    {
        $prefix = 'HS'.now()->format('ymd').'-';

        $last = Order::where('code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('code')
            ->value('code');

        $number = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }
}

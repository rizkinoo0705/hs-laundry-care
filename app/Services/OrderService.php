<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Mitra;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private StampService $stamps,
        private DeliveryFeeService $fees,
        private StaffCommissionService $staffCommissions,
    )
    {
    }

    /**
     * Buat order baru.
     *
     * $data: customer_name, customer_phone, customer_address?, mitra_id?, delivery_method, pickup?,
     *        items: [[service_id, qty], ...], discount?, notes?, pay_now?, payment_method?,
     *        weigh_later? (hanya untuk order jemput: berat diisi setelah cucian tiba di outlet)
     */
    public function create(array $data, ?User $user = null): Order
    {
        return DB::transaction(function () use ($data, $user) {
            $customer = $this->resolveCustomer($data);

            $pickup = ! empty($data['pickup']);
            $deliveryMethod = $data['delivery_method'] ?? 'ambil';
            $weighLater = ! empty($data['weigh_later']);

            if ($weighLater && ! $pickup) {
                throw ValidationException::withMessages([
                    'weigh_later' => 'Timbang belakangan hanya untuk order yang dijemput.',
                ]);
            }

            $calc = $weighLater ? $this->emptyCalc() : $this->buildItems($data['items'] ?? []);

            $discount = min((int) ($data['discount'] ?? 0), $calc['subtotal']);
            $laundryTotal = $calc['subtotal'] - $discount;

            // Ongkir antar-jemput: 1 perjalanan untuk jemput, 1 untuk antar.
            $trips = ($pickup ? 1 : 0) + ($deliveryMethod === 'antar' ? 1 : 0);
            $manualFee = isset($data['manual_delivery_fee']) && $data['manual_delivery_fee'] !== '' && $data['manual_delivery_fee'] !== null
                ? (int) $data['manual_delivery_fee']
                : null;
            $quote = $this->fees->quote($customer, $trips, $laundryTotal, $manualFee);

            // Komisi mitra hanya dari cucian, bukan dari ongkir.
            $mitra = ! empty($data['mitra_id']) ? Mitra::find($data['mitra_id']) : null;
            $commission = $mitra ? $mitra->commissionFor($calc['billed_kg'], $laundryTotal) : 0;

            $payNow = ! empty($data['pay_now']) && ! $weighLater;

            $order = Order::create([
                'code' => $this->nextCode(),
                'customer_id' => $customer->id,
                'mitra_id' => $mitra?->id,
                'user_id' => $user?->id,
                'staff_id' => $data['staff_id'] ?? null,
                'status' => $weighLater ? 'menunggu_timbang' : 'diterima',
                'delivery_method' => $deliveryMethod,
                'pickup' => $pickup,
                'distance_km' => $quote['distance_km'],
                'delivery_zone_id' => $quote['zone_id'],
                'delivery_trips' => $trips,
                'delivery_fee' => $quote['fee'],
                'delivery_discount' => $quote['discount'],
                'delivery_note' => $quote['note'],
                'subtotal' => $calc['subtotal'],
                'discount' => $discount,
                'total' => $laundryTotal + $quote['total'],
                'total_kg' => $calc['total_kg'],
                'payment_status' => $payNow ? 'lunas' : 'belum',
                'payment_method' => $payNow ? ($data['payment_method'] ?? 'tunai') : null,
                'paid_at' => $payNow ? now() : null,
                'due_at' => $weighLater ? null : now()->addHours($calc['max_hours'] ?: 48),
                'notes' => $data['notes'] ?? null,
                'commission_amount' => $commission,
            ]);

            $order->items()->createMany($calc['items']);

            return $order->load('items', 'customer', 'mitra');
        });
    }

    /**
     * Isi layanan & berat untuk order jemput yang baru ditimbang di outlet.
     * Menghitung ulang total, promo ongkir (syarat minimal belanja), dan komisi mitra.
     */
    public function fillItems(Order $order, array $items, int $discount = 0): Order
    {
        if ($order->status !== 'menunggu_timbang') {
            throw ValidationException::withMessages(['items' => 'Order ini sudah ditimbang.']);
        }

        return DB::transaction(function () use ($order, $items, $discount) {
            $order->loadMissing('customer', 'mitra');
            $calc = $this->buildItems($items);

            $discount = min($discount, $calc['subtotal']);
            $laundryTotal = $calc['subtotal'] - $discount;

            // Ongkir tetap sama seperti saat order dibuat; promo dihitung ulang
            // karena total cucian (syarat minimal belanja) baru diketahui sekarang.
            $quote = $this->fees->quote($order->customer, $order->delivery_trips, $laundryTotal, $order->delivery_fee);

            $order->items()->delete();
            $order->items()->createMany($calc['items']);

            $order->fill([
                'status' => 'diterima',
                'subtotal' => $calc['subtotal'],
                'discount' => $discount,
                'total_kg' => $calc['total_kg'],
                'delivery_fee' => $quote['fee'],
                'delivery_discount' => $quote['discount'],
                'delivery_note' => $quote['note'],
                'total' => $laundryTotal + $quote['total'],
                'due_at' => now()->addHours($calc['max_hours'] ?: 48),
                'commission_amount' => $order->mitra ? $order->mitra->commissionFor($calc['billed_kg'], $laundryTotal) : 0,
            ])->save();

            return $order->load('items');
        });
    }

    private function emptyCalc(): array
    {
        return ['items' => [], 'subtotal' => 0, 'total_kg' => 0.0, 'billed_kg' => 0.0, 'max_hours' => 0];
    }

    /** Hitung baris layanan: berat minimum, subtotal, total kg. */
    private function buildItems(array $rows): array
    {
        if (! $rows) {
            throw ValidationException::withMessages(['items' => 'Tambahkan minimal satu layanan.']);
        }

        $services = Service::whereIn('id', collect($rows)->pluck('service_id')->unique())->get()->keyBy('id');
        $calc = $this->emptyCalc();

        foreach ($rows as $row) {
            $service = $services->get($row['service_id']);
            if (! $service || ! $service->active) {
                throw ValidationException::withMessages(['items' => 'Layanan tidak ditemukan atau tidak aktif.']);
            }

            $qty = round((float) $row['qty'], 2);
            $charged = round($service->chargedQty($qty), 2);
            $lineTotal = (int) round($charged * $service->price);

            $calc['items'][] = [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'unit' => $service->unit,
                'qty' => $qty,
                'charged_qty' => $charged,
                'price' => $service->price,
                'subtotal' => $lineTotal,
            ];

            $calc['subtotal'] += $lineTotal;
            if ($service->unit === 'kg') {
                $calc['total_kg'] += $qty;
                $calc['billed_kg'] += $charged;
            }
            $calc['max_hours'] = max($calc['max_hours'], $service->duration_hours);
        }

        return $calc;
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
            $this->staffCommissions->recordForOrder($order);
            $this->autoScheduleDelivery($order);

            return $order;
        });
    }

    public function markPaid(Order $order, string $method, ?User $user = null): Order
    {
        if ($order->isPaid()) {
            return $order;
        }

        if ($order->status === 'menunggu_timbang') {
            throw ValidationException::withMessages(['payment_method' => 'Order belum ditimbang, total belum diketahui.']);
        }

        return DB::transaction(function () use ($order, $method, $user) {
            $order->update([
                'payment_status' => 'lunas',
                'payment_method' => $method,
                'paid_at' => now(),
            ]);

            $this->maybeAwardStamp($order, $user);
            $this->staffCommissions->recordForOrder($order);
            $this->autoScheduleDelivery($order);

            return $order;
        });
    }

    /**
     * Order antar yang sudah siap & lunas otomatis masuk daftar pengiriman
     * (tanpa kurir, menunggu kurir dipilih). Tidak dibuat dobel.
     */
    public function autoScheduleDelivery(Order $order): ?Delivery
    {
        if (! $order->readyForDelivery() || $order->hasActiveDelivery('antar')) {
            return null;
        }

        $order->loadMissing('customer');
        $customer = $order->customer;

        return Delivery::create([
            'type' => 'antar',
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'address' => $customer->address ?: 'Alamat belum diisi, hubungi pelanggan',
            'latitude' => $customer->latitude,
            'longitude' => $customer->longitude,
            'distance_km' => $customer->distance_km,
            'scheduled_at' => now(),
            'status' => 'terjadwal',
        ]);
    }

    /** Member dapat 1 stempel saat order selesai DAN lunas (sekali per order). */
    private function maybeAwardStamp(Order $order, ?User $user): void
    {
        $order->loadMissing('customer');

        if ($order->stamp_awarded
            || $order->status !== 'selesai'
            || ! $order->isPaid()
            || ! $order->customer->is_member
            || $order->laundryTotal() < config('laundry.stamp_min_total')) {
            return;
        }

        $this->stamps->add($order->customer, 1, 'Order '.$order->code, $order, $user);
        $order->stamp_awarded = true;
        $order->save();
    }

    public function resolveCustomer(array $data): Customer
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

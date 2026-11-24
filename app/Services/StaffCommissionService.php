<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Setting;
use App\Models\StaffCommission;
use App\Models\StaffPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Komisi petugas:
 *  - Pengerjaan: dicatat saat order SELESAI (diambil/diantar) DAN LUNAS, per kg/pcs yang ditagih x tarif layanan.
 *  - Jemput/antar: dicatat saat jadwal antar-jemput SELESAI, per perjalanan.
 * Tarif dikunci saat dicatat, jadi perubahan tarif tidak mengubah komisi lama.
 */
class StaffCommissionService
{
    public static function tripRate(string $type): int
    {
        return (int) Setting::get($type === 'antar' ? 'staff_rate_antar' : 'staff_rate_jemput', 0);
    }

    public function recordForOrder(Order $order): void
    {
        if ($order->status !== 'selesai' || ! $order->isPaid() || ! $order->staff_id) {
            return;
        }

        if (StaffCommission::where('order_id', $order->id)->where('type', 'layanan')->exists()) {
            return;
        }

        $order->loadMissing('items.service');
        // Dihitung pada saat syarat terakhir terpenuhi (selesai atau lunas).
        $earnedAt = max($order->completed_at ?? now(), $order->paid_at ?? now());

        foreach ($order->items as $item) {
            $rate = (int) ($item->service?->staff_commission ?? 0);
            if ($rate <= 0) {
                continue;
            }

            $qty = $item->billedQty();

            StaffCommission::create([
                'staff_id' => $order->staff_id,
                'order_id' => $order->id,
                'type' => 'layanan',
                'description' => $order->code.' · '.$item->service_name.' '.qty($qty).' '.$item->unit,
                'qty' => $qty,
                'rate' => $rate,
                'amount' => (int) round($qty * $rate),
                'earned_at' => $earnedAt,
            ]);
        }
    }

    /** Sinkronkan komisi kurir dengan status jadwal antar-jemput. */
    public function syncForDelivery(Delivery $delivery): void
    {
        $existing = StaffCommission::where('delivery_id', $delivery->id)->first();

        $shouldExist = $delivery->status === 'selesai' && $delivery->staff_id;

        if (! $shouldExist) {
            // Belum dibayar boleh dihapus; yang sudah dibayar dibiarkan.
            if ($existing && ! $existing->staff_payout_id) {
                $existing->delete();
            }

            return;
        }

        if ($existing) {
            if (! $existing->staff_payout_id && $existing->staff_id !== $delivery->staff_id) {
                $existing->update(['staff_id' => $delivery->staff_id]);
            }

            return;
        }

        $rate = self::tripRate($delivery->type);
        if ($rate <= 0) {
            return;
        }

        $delivery->loadMissing('customer', 'mitra', 'order');

        StaffCommission::create([
            'staff_id' => $delivery->staff_id,
            'order_id' => $delivery->order_id,
            'delivery_id' => $delivery->id,
            'type' => $delivery->type,
            'description' => ucfirst($delivery->type).' · '.$delivery->targetName().($delivery->order ? ' · '.$delivery->order->code : ''),
            'qty' => 1,
            'rate' => $rate,
            'amount' => $rate,
            'earned_at' => $delivery->completed_at ?? now(),
        ]);
    }

    /** Catat semua komisi tertunda petugas sebagai sudah dibayar. */
    public function payout(int $staffId, ?User $user, ?string $notes = null): ?StaffPayout
    {
        return DB::transaction(function () use ($staffId, $user, $notes) {
            $rows = StaffCommission::where('staff_id', $staffId)->whereNull('staff_payout_id')->lockForUpdate()->get();

            if ($rows->isEmpty()) {
                return null;
            }

            $payout = StaffPayout::create([
                'staff_id' => $staffId,
                'amount' => $rows->sum('amount'),
                'items_count' => $rows->count(),
                'paid_at' => now(),
                'paid_by' => $user?->id,
                'notes' => $notes,
            ]);

            StaffCommission::whereIn('id', $rows->pluck('id'))->update(['staff_payout_id' => $payout->id]);

            return $payout;
        });
    }
}

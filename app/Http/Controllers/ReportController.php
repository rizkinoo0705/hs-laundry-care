<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $from = ($request->date('from') ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($request->date('to') ?? now())->copy()->endOfDay();

        $orders = Order::active()->whereBetween('created_at', [$from, $to])->get();

        // Pemasukan dihitung dari tanggal pelunasan.
        $paid = Order::where('payment_status', 'lunas')->whereBetween('paid_at', [$from, $to])->get();

        $daily = [];
        foreach ($orders as $order) {
            $day = $order->created_at->toDateString();
            $daily[$day] ??= ['orders' => 0, 'kg' => 0.0, 'omzet' => 0, 'income' => 0];
            $daily[$day]['orders']++;
            $daily[$day]['kg'] += (float) $order->total_kg;
            $daily[$day]['omzet'] += $order->total;
        }
        foreach ($paid as $order) {
            $day = $order->paid_at->toDateString();
            $daily[$day] ??= ['orders' => 0, 'kg' => 0.0, 'omzet' => 0, 'income' => 0];
            $daily[$day]['income'] += $order->total;
        }
        krsort($daily);

        $byService = OrderItem::query()
            ->whereIn('order_id', $orders->pluck('id'))
            ->selectRaw('service_name, unit, sum(qty) as qty, sum(subtotal) as total, count(*) as lines')
            ->groupBy('service_name', 'unit')
            ->orderByDesc('total')
            ->get();

        $byMitra = Mitra::orderBy('name')->get()->map(function (Mitra $mitra) use ($orders) {
            $own = $orders->where('mitra_id', $mitra->id);

            return [
                'mitra' => $mitra,
                'orders' => $own->count(),
                'kg' => (float) $own->sum('total_kg'),
                'omzet' => (int) $own->sum('total'),
                'commission' => (int) $own->where('status', 'selesai')->sum('commission_amount'),
            ];
        })->filter(fn ($row) => $row['orders'] > 0)->values();

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'summary' => [
                'orders' => $orders->count(),
                'kg' => (float) $orders->sum('total_kg'),
                'omzet' => (int) $orders->sum('total'),
                'income' => (int) $paid->sum('total'),
                'unpaid' => (int) $orders->where('payment_status', 'belum')->sum('total'),
                'discount' => (int) $orders->sum('discount'),
                'commission' => (int) $orders->whereNotNull('mitra_id')->where('status', 'selesai')->sum('commission_amount'),
                'delivery_fee' => (int) $orders->sum('delivery_fee'),
                'delivery_discount' => (int) $orders->sum('delivery_discount'),
                'delivery_trips' => (int) $orders->sum('delivery_trips'),
            ],
            'daily' => $daily,
            'byService' => $byService,
            'byMitra' => $byMitra,
        ]);
    }
}

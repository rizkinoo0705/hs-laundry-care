<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->user()->role === 'mitra') {
            return redirect()->route('mitra.portal');
        }

        $today = now()->startOfDay();

        $statusCounts = Order::inProgress()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('dashboard', [
            'ordersToday' => Order::active()->where('created_at', '>=', $today)->count(),
            'kgToday' => (float) Order::active()->where('created_at', '>=', $today)->sum('total_kg'),
            'incomeToday' => (int) Order::where('payment_status', 'lunas')->where('paid_at', '>=', $today)->sum('total'),
            'unpaidTotal' => (int) Order::active()->where('payment_status', 'belum')->sum('total'),
            'statusCounts' => $statusCounts,
            'readyOrders' => Order::with('customer')->where('status', 'siap')->latest()->limit(10)->get(),
            'overdueOrders' => Order::with('customer')->inProgress()->where('due_at', '<', now())->orderBy('due_at')->limit(10)->get(),
            'deliveriesToday' => Delivery::with('customer', 'mitra')
                ->whereBetween('scheduled_at', [$today, $today->copy()->endOfDay()])
                ->whereNotIn('status', ['selesai', 'batal'])
                ->orderBy('scheduled_at')->get(),
            'memberCount' => Customer::where('is_member', true)->count(),
            'needCourier' => Delivery::whereNull('staff_id')->whereIn('status', ['terjadwal', 'jalan'])->count(),
        ]);
    }
}

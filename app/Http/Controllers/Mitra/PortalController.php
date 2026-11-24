<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function __invoke(Request $request)
    {
        $mitra = $request->user()->mitra;
        abort_unless($mitra, 403, 'Akun ini belum terhubung ke data mitra. Hubungi admin.');

        $monthStart = now()->startOfMonth();
        $monthOrders = $mitra->orders()->active()->where('created_at', '>=', $monthStart);

        return view('mitra.portal', [
            'mitra' => $mitra,
            'stats' => [
                'orders_month' => (clone $monthOrders)->count(),
                'kg_month' => (float) (clone $monthOrders)->sum('total_kg'),
                'commission_month' => (int) (clone $monthOrders)->where('status', 'selesai')->sum('commission_amount'),
                'unpaid' => $mitra->unpaidCommission(),
                'in_progress' => $mitra->orders()->inProgress()->count(),
            ],
            'orders' => $mitra->orders()->with('customer')->latest()->paginate(20),
            'payouts' => $mitra->payouts()->latest('paid_at')->limit(12)->get(),
        ]);
    }
}

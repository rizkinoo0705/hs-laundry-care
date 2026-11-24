<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\MitraPayout;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MitraController extends Controller
{
    public function index()
    {
        $mitras = Mitra::orderBy('name')
            ->withCount(['orders as orders_month' => fn ($q) => $q->active()->where('created_at', '>=', now()->startOfMonth())])
            ->withSum(['orders as kg_month' => fn ($q) => $q->active()->where('created_at', '>=', now()->startOfMonth())], 'total_kg')
            ->withSum(['orders as unpaid_commission' => fn ($q) => $q->commissionable()], 'commission_amount')
            ->get();

        return view('mitras.index', compact('mitras'));
    }

    public function create()
    {
        return view('mitras.form', ['mitra' => new Mitra([
            'active' => true,
            'commission_type' => config('laundry.default_commission_type'),
            'commission_value' => config('laundry.default_commission_value'),
        ])]);
    }

    public function store(Request $request)
    {
        $mitra = Mitra::create($this->validated($request));

        return redirect()->route('mitras.show', $mitra)->with('success', 'Mitra ditambahkan.');
    }

    public function show(Mitra $mitra)
    {
        return view('mitras.show', [
            'mitra' => $mitra,
            'pendingOrders' => $mitra->orders()->commissionable()->with('customer')->latest()->get(),
            'recentOrders' => $mitra->orders()->with('customer')->latest()->limit(20)->get(),
            'payouts' => $mitra->payouts()->with('payer')->latest('paid_at')->limit(20)->get(),
            'users' => $mitra->users()->get(),
        ]);
    }

    public function edit(Mitra $mitra)
    {
        return view('mitras.form', compact('mitra'));
    }

    public function update(Request $request, Mitra $mitra)
    {
        $mitra->update($this->validated($request));

        return redirect()->route('mitras.show', $mitra)->with('success', 'Data mitra disimpan.');
    }

    /** Cairkan semua komisi yang tertunda untuk mitra ini. */
    public function payout(Request $request, Mitra $mitra)
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        $payout = DB::transaction(function () use ($mitra, $data, $request) {
            $orders = Order::where('mitra_id', $mitra->id)->commissionable()->lockForUpdate()->get();

            if ($orders->isEmpty()) {
                return null;
            }

            $payout = MitraPayout::create([
                'mitra_id' => $mitra->id,
                'amount' => $orders->sum('commission_amount'),
                'total_kg' => $orders->sum('total_kg'),
                'orders_count' => $orders->count(),
                'paid_at' => now(),
                'paid_by' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
            ]);

            Order::whereIn('id', $orders->pluck('id'))->update(['mitra_payout_id' => $payout->id]);

            return $payout;
        });

        if (! $payout) {
            return back()->withErrors(['payout' => 'Tidak ada komisi yang bisa dicairkan.']);
        }

        return back()->with('success', 'Komisi '.rupiah($payout->amount).' dicatat sudah dibayar.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'owner_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'commission_type' => ['required', Rule::in(array_keys(Mitra::COMMISSION_TYPES))],
            'commission_value' => ['required', 'integer', 'min:0',
                Rule::when($request->input('commission_type') === 'percent', ['max:100'])],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');

        return $data;
    }
}

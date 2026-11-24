<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Staff;
use App\Services\StaffCommissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index()
    {
        $monthStart = now()->startOfMonth();

        $staff = Staff::orderByDesc('active')->orderBy('name')
            ->withSum(['commissions as unpaid' => fn ($q) => $q->whereNull('staff_payout_id')], 'amount')
            ->withSum(['commissions as month_total' => fn ($q) => $q->where('earned_at', '>=', $monthStart)], 'amount')
            ->get();

        return view('staff.index', [
            'staff' => $staff,
            'rateJemput' => StaffCommissionService::tripRate('jemput'),
            'rateAntar' => StaffCommissionService::tripRate('antar'),
        ]);
    }

    public function create()
    {
        return view('staff.form', ['member' => new Staff]);
    }

    public function store(Request $request)
    {
        $member = Staff::create($this->validated($request));

        return redirect()->route('staff.show', $member)->with('success', 'Petugas ditambahkan.');
    }

    public function show(Request $request, Staff $staff)
    {
        $from = ($request->date('from') ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($request->date('to') ?? now())->copy()->endOfDay();

        $period = $staff->commissions()->with('order')->whereBetween('earned_at', [$from, $to])->latest('earned_at')->get();

        return view('staff.show', [
            'member' => $staff,
            'unpaid' => $staff->commissions()->with('order')->whereNull('staff_payout_id')->latest('earned_at')->get(),
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'payouts' => $staff->payouts()->with('payer')->latest('paid_at')->limit(20)->get(),
        ]);
    }

    public function edit(Staff $staff)
    {
        return view('staff.form', ['member' => $staff]);
    }

    public function update(Request $request, Staff $staff)
    {
        $staff->update($this->validated($request));

        return redirect()->route('staff.show', $staff)->with('success', 'Data petugas disimpan.');
    }

    public function payout(Request $request, Staff $staff, StaffCommissionService $commissions)
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $payout = $commissions->payout($staff->id, $request->user(), $data['notes'] ?? null);

        if (! $payout) {
            return back()->withErrors(['payout' => 'Tidak ada komisi yang belum dibayar.']);
        }

        return back()->with('success', 'Komisi '.rupiah($payout->amount).' dicatat sudah dibayar ke '.$staff->name.'.');
    }

    public function updateRates(Request $request)
    {
        $data = $request->validate([
            'staff_rate_jemput' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'staff_rate_antar' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        Setting::set('staff_rate_jemput', (int) ($data['staff_rate_jemput'] ?? 0));
        Setting::set('staff_rate_antar', (int) ($data['staff_rate_antar'] ?? 0));

        return back()->with('success', 'Tarif komisi jemput/antar disimpan.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'position' => ['required', Rule::in(array_keys(Staff::POSITIONS))],
            'active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['active'] = $request->boolean('active');

        return $data;
    }
}

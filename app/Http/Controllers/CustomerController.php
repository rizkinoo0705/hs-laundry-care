<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\StampService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()->withCount('orders')->orderBy('name');

        if ($search = trim((string) $request->query('q'))) {
            $digits = preg_replace('/\D+/', '', $search);
            $query->where(function ($q) use ($search, $digits) {
                $q->whereLike('name', "%{$search}%")
                    ->orWhereLike('member_code', "%{$search}%");
                if ($digits !== '') {
                    $q->orWhereLike('phone', "%{$digits}%");
                }
            });
        }

        if ($request->query('member') === '1') {
            $query->where('is_member', true);
        }

        return view('customers.index', [
            'customers' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('customers.form', ['customer' => new Customer]);
    }

    public function store(Request $request)
    {
        $customer = Customer::create($this->validated($request));

        return redirect()->route('customers.show', $customer)->with('success', 'Pelanggan ditambahkan.');
    }

    public function show(Customer $customer)
    {
        return view('customers.show', [
            'customer' => $customer,
            'orders' => $customer->orders()->latest()->limit(20)->get(),
            'stampLogs' => $customer->stampTransactions()->latest()->limit(20)->get(),
        ]);
    }

    public function edit(Customer $customer)
    {
        return view('customers.form', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request, $customer));

        return redirect()->route('customers.show', $customer)->with('success', 'Data pelanggan disimpan.');
    }

    public function redeem(Request $request, Customer $customer, StampService $stamps)
    {
        $stamps->redeem($customer, $request->user());

        return back()->with('success', 'Hadiah ditukar. Stempel dikurangi '.config('laundry.stamp_target').'.');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'min:9', 'max:20', Rule::unique('customers', 'phone')->ignore($customer?->id)],
            'address' => ['nullable', 'string', 'max:500'],
            'is_member' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'phone.unique' => 'Nomor HP ini sudah terdaftar.',
        ]);

        $data['is_member'] = $request->boolean('is_member');

        return $data;
    }
}

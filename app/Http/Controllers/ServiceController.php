<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index()
    {
        return view('services.index', [
            'services' => Service::orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('services.form', ['service' => new Service(['active' => true, 'duration_hours' => 48])]);
    }

    public function store(Request $request)
    {
        Service::create($this->validated($request));

        return redirect()->route('services.index')->with('success', 'Layanan ditambahkan.');
    }

    public function edit(Service $service)
    {
        return view('services.form', compact('service'));
    }

    public function update(Request $request, Service $service)
    {
        $service->update($this->validated($request));

        return redirect()->route('services.index')->with('success', 'Layanan disimpan.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::in(array_keys(Service::CATEGORIES))],
            'unit' => ['required', Rule::in(array_keys(Service::UNITS))],
            'price' => ['required', 'integer', 'min:0'],
            'min_qty' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'staff_commission' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'duration_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');
        $data['staff_commission'] = (int) ($data['staff_commission'] ?? 0);
        $data['min_qty'] = $request->filled('min_qty') && (float) $request->input('min_qty') > 0
            ? $request->input('min_qty')
            : null;

        return $data;
    }
}

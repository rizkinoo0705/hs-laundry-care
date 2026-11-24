<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', [
            'users' => User::with('mitra')->orderBy('role')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('users.form', [
            'user' => new User(['role' => 'kasir', 'active' => true]),
            'mitras' => Mitra::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        User::create($this->validated($request));

        return redirect()->route('users.index')->with('success', 'Pengguna ditambahkan.');
    }

    public function edit(User $user)
    {
        return view('users.form', [
            'user' => $user,
            'mitras' => Mitra::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && ($data['role'] !== 'admin' || ! $data['active'])) {
            return back()->withInput()->withErrors(['role' => 'Anda tidak bisa menurunkan atau menonaktifkan akun sendiri.']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'Pengguna disimpan.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'mitra_id' => ['nullable', 'required_if:role,mitra', Rule::exists('mitras', 'id')],
            'active' => ['nullable', 'boolean'],
        ], [
            'mitra_id.required_if' => 'Pilih mitra untuk akun mitra.',
        ]);

        $data['active'] = $request->boolean('active');
        if ($data['role'] !== 'mitra') {
            $data['mitra_id'] = null;
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}

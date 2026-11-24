@extends('layouts.app')
@section('title', $user->exists ? 'Ubah Pengguna' : 'Pengguna Baru')

@section('content')
<div class="page-head">
    <h1>{{ $user->exists ? 'Ubah Pengguna' : 'Pengguna Baru' }}</h1>
</div>

<div class="card" style="max-width: 640px">
    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
        @csrf
        @if ($user->exists) @method('PUT') @endif

        <div class="field">
            <label for="name">Nama</label>
            <input type="text" id="name" name="name" required value="{{ old('name', $user->name) }}">
        </div>
        <div class="field">
            <label for="email">Email (untuk login)</label>
            <input type="email" id="email" name="email" required value="{{ old('email', $user->email) }}">
        </div>
        <div class="field">
            <label for="password">Password {{ $user->exists ? '(kosongkan jika tidak diganti)' : '' }}</label>
            <input type="password" id="password" name="password" {{ $user->exists ? '' : 'required' }} minlength="8" autocomplete="new-password">
        </div>
        <div class="grid grid-2" style="gap: 12px">
            <div class="field">
                <label for="role">Peran</label>
                <select id="role" name="role">
                    @foreach (\App\Models\User::ROLES as $key => $label)
                        <option value="{{ $key }}" @selected(old('role', $user->role) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" id="mitra-wrap">
                <label for="mitra_id">Mitra</label>
                <select id="mitra_id" name="mitra_id">
                    <option value="">Pilih mitra</option>
                    @foreach ($mitras as $m)
                        <option value="{{ $m->id }}" @selected((string) old('mitra_id', $user->mitra_id) === (string) $m->id)>{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <label class="check"><input type="checkbox" name="active" value="1" @checked(old('active', $user->active))> Aktif (boleh login)</label>

        <div class="row" style="margin-top: 10px">
            <button type="submit" class="btn">Simpan</button>
            <a href="{{ route('users.index') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const role = document.getElementById('role');
    const toggle = () => document.getElementById('mitra-wrap').style.display = role.value === 'mitra' ? '' : 'none';
    role.addEventListener('change', toggle);
    toggle();
})();
</script>
@endpush

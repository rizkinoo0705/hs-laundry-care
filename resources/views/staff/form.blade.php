@extends('layouts.app')
@section('title', $member->exists ? 'Ubah Petugas' : 'Petugas Baru')

@section('content')
<div class="page-head">
    <h1>{{ $member->exists ? 'Ubah Petugas' : 'Petugas Baru' }}</h1>
</div>

<div class="card" style="max-width: 640px">
    <form method="POST" action="{{ $member->exists ? route('staff.update', $member) : route('staff.store') }}">
        @csrf
        @if ($member->exists) @method('PUT') @endif

        <div class="field">
            <label for="name">Nama</label>
            <input type="text" id="name" name="name" required value="{{ old('name', $member->name) }}">
        </div>
        <div class="grid grid-2" style="gap: 12px">
            <div class="field">
                <label for="phone">No. HP / WhatsApp</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone', $member->phone) }}">
            </div>
            <div class="field">
                <label for="position">Posisi</label>
                <select id="position" name="position">
                    @foreach (\App\Models\Staff::POSITIONS as $key => $label)
                        <option value="{{ $key }}" @selected(old('position', $member->position) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="field">
            <label for="notes">Catatan</label>
            <textarea id="notes" name="notes" rows="2">{{ old('notes', $member->notes) }}</textarea>
        </div>
        <label class="check"><input type="checkbox" name="active" value="1" @checked(old('active', $member->active))> Aktif (muncul di pilihan petugas/kurir)</label>

        <div class="row" style="margin-top: 10px">
            <button type="submit" class="btn">Simpan</button>
            <a href="{{ $member->exists ? route('staff.show', $member) : route('staff.index') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>
@endsection

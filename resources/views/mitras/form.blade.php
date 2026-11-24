@extends('layouts.app')
@section('title', $mitra->exists ? 'Ubah Mitra' : 'Mitra Baru')

@section('content')
<div class="page-head">
    <h1>{{ $mitra->exists ? 'Ubah Mitra' : 'Mitra Baru' }}</h1>
</div>

<div class="card" style="max-width: 640px">
    <form method="POST" action="{{ $mitra->exists ? route('mitras.update', $mitra) : route('mitras.store') }}">
        @csrf
        @if ($mitra->exists) @method('PUT') @endif

        <div class="field">
            <label for="name">Nama outlet mitra</label>
            <input type="text" id="name" name="name" required value="{{ old('name', $mitra->name) }}" placeholder="Mis. Warung Bu Sri">
        </div>
        <div class="grid grid-2" style="gap: 12px">
            <div class="field">
                <label for="owner_name">Nama pemilik</label>
                <input type="text" id="owner_name" name="owner_name" value="{{ old('owner_name', $mitra->owner_name) }}">
            </div>
            <div class="field">
                <label for="phone">No. HP / WhatsApp</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone', $mitra->phone) }}">
            </div>
        </div>
        <div class="field">
            <label for="address">Alamat</label>
            <textarea id="address" name="address" rows="2">{{ old('address', $mitra->address) }}</textarea>
        </div>
        <div class="grid grid-2" style="gap: 12px">
            <div class="field">
                <label for="commission_type">Jenis komisi</label>
                <select id="commission_type" name="commission_type">
                    @foreach (\App\Models\Mitra::COMMISSION_TYPES as $key => $label)
                        <option value="{{ $key }}" @selected(old('commission_type', $mitra->commission_type) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="commission_value">Nilai komisi</label>
                <input type="number" id="commission_value" name="commission_value" min="0" required value="{{ old('commission_value', $mitra->commission_value) }}" inputmode="numeric">
                <div class="hint">Rupiah per kg (mis. 1000), atau persen (mis. 10).</div>
            </div>
        </div>
        <label class="check"><input type="checkbox" name="active" value="1" @checked(old('active', $mitra->active))> Aktif</label>
        @if ($mitra->exists)
            <div class="hint">Perubahan komisi hanya berlaku untuk order baru.</div>
        @endif

        <div class="row" style="margin-top: 10px">
            <button type="submit" class="btn">Simpan</button>
            <a href="{{ $mitra->exists ? route('mitras.show', $mitra) : route('mitras.index') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>
@endsection

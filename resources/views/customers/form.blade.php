@extends('layouts.app')
@section('title', $customer->exists ? 'Ubah Pelanggan' : 'Pelanggan Baru')

@section('content')
<div class="page-head">
    <h1>{{ $customer->exists ? 'Ubah Pelanggan' : 'Pelanggan Baru' }}</h1>
</div>

<div class="card" style="max-width: 640px">
    <form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}">
        @csrf
        @if ($customer->exists) @method('PUT') @endif

        <div class="field">
            <label for="name">Nama</label>
            <input type="text" id="name" name="name" required value="{{ old('name', $customer->name) }}">
        </div>
        <div class="field">
            <label for="phone">No. HP / WhatsApp</label>
            <input type="tel" id="phone" name="phone" required value="{{ old('phone', $customer->phone) }}" placeholder="08xxxxxxxxxx">
        </div>
        <div class="field">
            <label for="address">Alamat</label>
            <textarea id="address" name="address" rows="2">{{ old('address', $customer->address) }}</textarea>
        </div>
        <label class="check"><input type="checkbox" name="is_member" value="1" @checked(old('is_member', $customer->is_member))> Jadikan member (dapat kartu stempel)</label>
        @if ($customer->member_code)<div class="hint">Kode member: {{ $customer->member_code }}</div>@endif
        <div class="field" style="margin-top: 10px">
            <label for="notes">Catatan</label>
            <textarea id="notes" name="notes" rows="2">{{ old('notes', $customer->notes) }}</textarea>
        </div>

        <div class="row">
            <button type="submit" class="btn">Simpan</button>
            <a href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>
@endsection

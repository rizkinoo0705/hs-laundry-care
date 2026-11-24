@extends('layouts.app')
@section('title', 'Jadwal Antar-Jemput')

@section('content')
<div class="page-head">
    <h1>Jadwal Antar-Jemput</h1>
</div>

@php($target = old('target', 'customer'))
<div class="card" style="max-width: 640px">
    <form method="POST" action="{{ route('deliveries.store') }}">
        @csrf
        @if ($order)
            <input type="hidden" name="order_id" value="{{ $order->id }}">
            <p>Untuk order <a href="{{ route('orders.show', $order) }}" class="strong">{{ $order->code }}</a></p>
        @endif

        <div class="grid grid-2" style="gap: 12px">
            <div class="field">
                <label for="type">Jenis</label>
                <select id="type" name="type">
                    @foreach (\App\Models\Delivery::TYPES as $key => $label)
                        <option value="{{ $key }}" @selected(old('type', $order ? 'antar' : 'jemput') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="scheduled_at">Waktu</label>
                <input type="datetime-local" id="scheduled_at" name="scheduled_at" required value="{{ old('scheduled_at', now()->addHour()->format('Y-m-d\TH:00')) }}">
            </div>
        </div>

        <div class="field">
            <label for="target">Tujuan</label>
            <select id="target" name="target">
                <option value="customer" @selected($target === 'customer')>Pelanggan</option>
                <option value="mitra" @selected($target === 'mitra')>Mitra Dropbox</option>
            </select>
        </div>

        <div class="field" id="customer-wrap">
            <label for="customer_id">Pelanggan</label>
            <select id="customer_id" name="customer_id">
                <option value="">Pilih pelanggan</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->id }}" data-address="{{ $c->address }}" @selected((string) old('customer_id', $order?->customer_id) === (string) $c->id)>{{ $c->name }} - {{ \App\Support\Phone::format($c->phone) }}</option>
                @endforeach
            </select>
        </div>

        <div class="field" id="mitra-wrap">
            <label for="mitra_id">Mitra</label>
            <select id="mitra_id" name="mitra_id">
                <option value="">Pilih mitra</option>
                @foreach ($mitras as $m)
                    <option value="{{ $m->id }}" data-address="{{ $m->address }}" @selected((string) old('mitra_id') === (string) $m->id)>{{ $m->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="address">Alamat</label>
            <textarea id="address" name="address" rows="2" placeholder="Kosongkan untuk memakai alamat tersimpan">{{ old('address') }}</textarea>
        </div>

        <div class="field">
            <label for="staff_id">Kurir</label>
            <select id="staff_id" name="staff_id">
                <option value="">Belum ditentukan</option>
                @foreach ($couriers as $c)
                    <option value="{{ $c->id }}" @selected((string) old('staff_id') === (string) $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
            <div class="hint">Komisi jemput/antar masuk ke kurir ini saat jadwal ditandai selesai.</div>
        </div>

        <div class="field">
            <label for="notes">Catatan</label>
            <input type="text" id="notes" name="notes" value="{{ old('notes') }}" placeholder="Mis. rumah pagar hijau">
        </div>

        <div class="row">
            <button type="submit" class="btn">Simpan Jadwal</button>
            <a href="{{ route('deliveries.index') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const target = document.getElementById('target');
    const address = document.getElementById('address');
    const toggle = () => {
        document.getElementById('customer-wrap').style.display = target.value === 'customer' ? '' : 'none';
        document.getElementById('mitra-wrap').style.display = target.value === 'mitra' ? '' : 'none';
    };
    const fill = (e) => {
        const opt = e.target.selectedOptions[0];
        if (opt && opt.dataset.address && !address.value) address.value = opt.dataset.address;
    };
    target.addEventListener('change', toggle);
    document.getElementById('customer_id').addEventListener('change', fill);
    document.getElementById('mitra_id').addEventListener('change', fill);
    toggle();
})();
</script>
@endpush

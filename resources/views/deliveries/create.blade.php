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
        <div class="grid grid-2" style="gap: 12px">
            <div class="field">
                <label for="type">Jenis</label>
                <select id="type" name="type">
                    @foreach (\App\Models\Delivery::TYPES as $key => $label)
                        <option value="{{ $key }}" @selected(old('type', $order && $order->status === 'siap' ? 'antar' : 'jemput') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="scheduled_at">Waktu</label>
                <input type="datetime-local" id="scheduled_at" name="scheduled_at" required value="{{ old('scheduled_at', now()->addHour()->format('Y-m-d\TH:00')) }}">
            </div>
        </div>

        <div class="field" id="order-wrap">
            <label for="order_id">No. order <span id="order-required" class="muted">(wajib untuk antar)</span></label>
            <select id="order_id" name="order_id">
                <option value="">Pilih order</option>
                @foreach ([['antar', $antarOrders], ['jemput', $jemputOrders]] as [$kind, $list])
                    @foreach ($list as $o)
                        <option value="{{ $o->id }}" data-kind="{{ $kind }}" data-customer="{{ $o->customer_id }}" data-mitra="{{ $o->mitra_id }}" data-address="{{ $o->customer->address }}"
                            @selected((string) old('order_id', $order?->id) === (string) $o->id)>
                            {{ $o->code }} · {{ $o->customer->name }} · {{ $o->tripsLabel() }}
                        </option>
                    @endforeach
                @endforeach
            </select>
            <div class="hint" id="order-hint"></div>
            @error('order_id')<div class="error">{{ $message }}</div>@enderror
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

    // Jenis "antar" wajib memilih order; pilih order mengisi pelanggan otomatis.
    const type = document.getElementById('type');
    const orderSel = document.getElementById('order_id');
    const toggleOrder = () => {
        const antar = type.value === 'antar';
        const kind = antar ? 'antar' : 'jemput';
        let available = 0;
        orderSel.querySelectorAll('option[data-kind]').forEach((opt) => {
            const show = opt.dataset.kind === kind;
            opt.hidden = !show;
            opt.disabled = !show;
            if (show) available++;
            if (!show && opt.selected) orderSel.value = '';
        });
        orderSel.required = antar;
        document.getElementById('order-required').textContent = antar ? '(wajib)' : '(opsional)';
        document.getElementById('order-hint').textContent = antar
            ? (available ? 'Order berstatus "Siap Diambil/Antar", sudah lunas, dengan layanan Antar atau Jemput + antar.' : 'Belum ada order yang siap diantar. Order antar yang siap & lunas otomatis masuk daftar pengiriman.')
            : 'Opsional. Order dengan layanan Jemput / Jemput + antar yang belum dijemput.';
    };
    orderSel.addEventListener('change', () => {
        const opt = orderSel.selectedOptions[0];
        if (!opt || !opt.value) return;
        if (target.value === 'customer' || !opt.dataset.mitra) {
            target.value = 'customer';
            document.getElementById('customer_id').value = opt.dataset.customer;
            toggle();
        }
        if (opt.dataset.address && !address.value) address.value = opt.dataset.address;
    });
    type.addEventListener('change', toggleOrder);
    toggleOrder();
    document.getElementById('customer_id').addEventListener('change', fill);
    document.getElementById('mitra_id').addEventListener('change', fill);
    toggle();
})();
</script>
@endpush

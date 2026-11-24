@extends('layouts.app')
@section('title', 'Order Baru')
@section('hide-bottom-nav', '1')

@section('content')
<div class="page-head">
    <h1>Order Baru</h1>
</div>

<form method="POST" action="{{ route('orders.store') }}" id="order-form" class="has-mobile-submit">
    @csrf
    <div class="grid grid-2">
        <div class="stack">
            <div class="card">
                <h2>Pelanggan</h2>
                <div class="field">
                    <label for="customer_phone">No. HP / WhatsApp</label>
                    <input type="tel" id="customer_phone" name="customer_phone" list="customer-list" required
                           value="{{ old('customer_phone', $selectedCustomer?->phone) }}" placeholder="08xxxxxxxxxx" autocomplete="off">
                    <datalist id="customer-list">
                        @foreach ($customers as $c)
                            <option value="{{ $c->phone }}">{{ $c->name }}</option>
                        @endforeach
                    </datalist>
                    <div class="hint" id="customer-hint">Ketik nomor. Pelanggan lama akan terisi otomatis.</div>
                </div>
                <div class="field">
                    <label for="customer_name">Nama</label>
                    <input type="text" id="customer_name" name="customer_name" required value="{{ old('customer_name', $selectedCustomer?->name) }}">
                </div>
                <div class="field">
                    <label for="customer_address">Alamat <span class="muted">(untuk antar-jemput)</span></label>
                    <textarea id="customer_address" name="customer_address" rows="2">{{ old('customer_address', $selectedCustomer?->address) }}</textarea>
                </div>
            </div>

            <div class="card">
                <h2>Antar-jemput &amp; mitra</h2>
                <label class="check"><input type="checkbox" name="pickup" value="1" id="pickup" @checked(old('pickup'))> Cucian dijemput ke rumah pelanggan</label>
                <div class="field">
                    <label for="delivery_method">Setelah selesai</label>
                    <select id="delivery_method" name="delivery_method">
                        @foreach (\App\Models\Order::DELIVERY_METHODS as $key => $label)
                            <option value="{{ $key }}" @selected(old('delivery_method', 'ambil') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fee-box" id="fee-box" hidden>
                    <div id="fee-info">Menghitung ongkir…</div>
                    <div class="field" id="manual-fee-wrap" style="margin: 8px 0 0">
                        <label for="manual_delivery_fee">Ongkir manual (Rp, opsional)</label>
                        <input type="number" id="manual_delivery_fee" name="manual_delivery_fee" min="0" step="500" value="{{ old('manual_delivery_fee') }}" inputmode="numeric" placeholder="Kosongkan untuk tarif zona">
                        <div class="hint">Diisi jika pelanggan di luar zona/belum punya titik lokasi, atau ongkir disepakati lain.</div>
                    </div>
                </div>
                <div class="field">
                    <label for="mitra_id">Dititip lewat Mitra Dropbox</label>
                    <select id="mitra_id" name="mitra_id">
                        <option value="">Tidak (langsung ke outlet)</option>
                        @foreach ($mitras as $m)
                            <option value="{{ $m->id }}" @selected((string) old('mitra_id') === (string) $m->id)>{{ $m->name }} ({{ $m->commissionLabel() }})</option>
                        @endforeach
                    </select>
                    <div class="hint">Komisi mitra dihitung otomatis dari order ini.</div>
                </div>
                <div class="field">
                    <label for="notes">Catatan</label>
                    <textarea id="notes" name="notes" rows="2" placeholder="Mis. pisahkan baju putih, jangan pakai pewangi">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <h2>Layanan</h2>
                <div id="items">
                    @php($oldItems = old('items', [['service_id' => $services->first()?->id, 'qty' => '']]))
                    @foreach ($oldItems as $i => $item)
                        <div class="item-row">
                            <div>
                                <label>Layanan</label>
                                <select name="items[{{ $i }}][service_id]" class="js-service" required>
                                    @foreach ($services as $s)
                                        <option value="{{ $s->id }}" data-price="{{ $s->price }}" data-unit="{{ $s->unit }}" data-min="{{ $s->hasMinimum() ? (float) $s->min_qty : 0 }}"
                                            @selected((string) ($item['service_id'] ?? '') === (string) $s->id)>
                                            {{ $s->name }} - {{ rupiah($s->price) }}/{{ $s->unit }}@if ($s->hasMinimum()) (min. {{ qty($s->min_qty) }} {{ $s->unit }})@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label>Jumlah <span class="js-unit muted"></span></label>
                                <input type="number" name="items[{{ $i }}][qty]" class="js-qty" step="0.1" min="0.1" required value="{{ $item['qty'] ?? '' }}" inputmode="decimal">
                            </div>
                            <div class="hint js-min-hint" style="grid-column: 1 / -1; margin-top: -4px"></div>
                            <div class="line-total js-line">Rp0</div>
                            <div><button type="button" class="btn btn-danger btn-sm js-remove" aria-label="Hapus baris" style="min-height: 44px">×</button></div>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="btn btn-outline btn-sm" id="add-item">+ Tambah layanan</button>
            </div>

            <div class="card">
                <h2>Pembayaran</h2>
                <div class="field">
                    <label for="discount">Diskon (Rp)</label>
                    <input type="number" id="discount" name="discount" min="0" step="500" value="{{ old('discount', 0) }}" inputmode="numeric">
                </div>
                <div class="total-box" style="margin-bottom: 14px">
                    <div class="small muted">Total bayar</div>
                    <div class="big" id="grand-total">Rp0</div>
                    <div class="small muted" id="kg-total"></div>
                    <div class="small muted" id="ongkir-line"></div>
                </div>
                <label class="check"><input type="checkbox" name="pay_now" value="1" id="pay_now" @checked(old('pay_now'))> Bayar sekarang</label>
                <div class="field" id="pay-method-wrap">
                    <label for="payment_method">Metode</label>
                    <select id="payment_method" name="payment_method">
                        @foreach (\App\Models\Order::PAYMENT_METHODS as $key => $label)
                            <option value="{{ $key }}" @selected(old('payment_method', 'tunai') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-block desktop-submit">Simpan Order</button>
            </div>
        </div>
    </div>

    <div class="mobile-submit">
        <div>
            <div class="mt-label">Total bayar</div>
            <div class="mt-total" id="grand-total-mobile">Rp0</div>
        </div>
        <button type="submit" class="btn">Simpan Order</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const customers = @json($customers->keyBy('phone'));
    const itemsEl = document.getElementById('items');
    const rupiah = (n) => 'Rp' + Math.round(n).toLocaleString('id-ID');
    const normalize = (p) => {
        let d = (p || '').replace(/\D+/g, '');
        if (d.startsWith('62')) d = '0' + d.slice(2);
        else if (d.startsWith('8')) d = '0' + d;
        return d;
    };

    document.getElementById('customer_phone').addEventListener('change', function () {
        const c = customers[normalize(this.value)];
        const hint = document.getElementById('customer-hint');
        if (c) {
            document.getElementById('customer_name').value = c.name;
            if (c.address) document.getElementById('customer_address').value = c.address;
            hint.textContent = 'Pelanggan lama: ' + c.name;
        } else {
            hint.textContent = 'Pelanggan baru akan dibuat otomatis.';
        }
    });

    // ----- Ongkir antar-jemput (dihitung server) -----
    let laundryTotal = 0, ongkir = null, quoteTimer = null, lastQuoteKey = '';
    const feeBox = document.getElementById('fee-box');
    const feeInfo = document.getElementById('fee-info');
    const quoteUrl = @json(route('orders.quote'));

    function scheduleQuote() {
        clearTimeout(quoteTimer);
        quoteTimer = setTimeout(fetchQuote, 350);
    }

    function fetchQuote() {
        const pickup = document.getElementById('pickup').checked;
        const method = document.getElementById('delivery_method').value;
        const manual = document.getElementById('manual_delivery_fee').value;
        const phone = document.getElementById('customer_phone').value;
        if (!pickup && method !== 'antar') {
            feeBox.hidden = true;
            if (ongkir) { ongkir = null; recalc(); }
            lastQuoteKey = '';
            return;
        }
        feeBox.hidden = false;
        const params = new URLSearchParams({ phone: phone, pickup: pickup ? 1 : 0, delivery_method: method, laundry_total: laundryTotal });
        if (manual !== '') params.set('manual_delivery_fee', manual);
        const key = params.toString();
        if (key === lastQuoteKey) return;
        lastQuoteKey = key;
        fetch(quoteUrl + '?' + key, { headers: { 'Accept': 'application/json' } })
            .then((r) => r.json())
            .then((q) => { ongkir = q; showQuote(q); recalc(); })
            .catch(() => { feeInfo.textContent = 'Ongkir gagal dihitung. Isi ongkir manual bila perlu.'; });
    }

    function showQuote(q) {
        const parts = [];
        const trips = q.trips + ' perjalanan (' + [document.getElementById('pickup').checked ? 'jemput' : null,
            document.getElementById('delivery_method').value === 'antar' ? 'antar' : null].filter(Boolean).join(' + ') + ')';
        if (q.manual) {
            parts.push('<strong>Ongkir manual ' + rupiah(q.fee) + '</strong> · ' + trips);
        } else if (q.no_location) {
            parts.push('<span class="warn">' + (q.customer_found ? 'Pelanggan belum punya titik lokasi.' : 'Pelanggan baru, lokasi belum diatur.')
                + ' Isi ongkir manual, atau atur lokasinya di halaman pelanggan.</span>');
        } else if (q.out_of_zone) {
            parts.push('<span class="warn">Jarak ' + Number(q.distance_km).toLocaleString('id-ID') + ' km: di luar zona tarif. Isi ongkir manual.</span>');
        } else {
            parts.push('<strong>' + q.zone_name + '</strong> · ' + Number(q.distance_km).toLocaleString('id-ID') + ' km · '
                + rupiah(q.fee_per_trip) + ' × ' + trips + ' = <strong>' + rupiah(q.fee) + '</strong>');
        }
        if (q.discount > 0) {
            parts.push('<span style="color:#15803D">' + q.note + ': −' + rupiah(q.discount) + '</span>');
        }
        if (q.fee > 0 || q.manual) parts.push('Ongkir dibayar: <strong>' + rupiah(q.total) + '</strong>');
        feeInfo.innerHTML = parts.join('<br>');
    }

    ['pickup', 'delivery_method'].forEach((id) => document.getElementById(id).addEventListener('change', scheduleQuote));
    document.getElementById('manual_delivery_fee').addEventListener('input', scheduleQuote);
    document.getElementById('customer_phone').addEventListener('change', scheduleQuote);

    function recalc() {
        let total = 0, kg = 0;
        itemsEl.querySelectorAll('.item-row').forEach((row) => {
            const opt = row.querySelector('.js-service').selectedOptions[0];
            const price = opt ? Number(opt.dataset.price) : 0;
            const unit = opt ? opt.dataset.unit : '';
            const min = opt ? Number(opt.dataset.min) || 0 : 0;
            const q = parseFloat(row.querySelector('.js-qty').value) || 0;
            const charged = q > 0 && min > 0 ? Math.max(q, min) : q;
            const fmt = (n) => n.toLocaleString('id-ID');
            row.querySelector('.js-unit').textContent = unit ? '(' + unit + ')' : '';
            row.querySelector('.js-line').textContent = rupiah(price * charged);
            row.querySelector('.js-min-hint').textContent = (q > 0 && charged > q)
                ? 'Di bawah minimum: ditagih ' + fmt(min) + ' ' + unit + ' = ' + rupiah(price * charged)
                : (min > 0 ? 'Minimum ' + fmt(min) + ' ' + unit : '');
            total += Math.round(price * charged);
            if (unit === 'kg') kg += q;
        });
        const discount = Math.min(Number(document.getElementById('discount').value) || 0, total);
        laundryTotal = total - discount;
        const grand = laundryTotal + (ongkir ? ongkir.total : 0);
        document.getElementById('grand-total').textContent = rupiah(grand);
        document.getElementById('grand-total-mobile').textContent = rupiah(grand);
        document.getElementById('ongkir-line').textContent = ongkir && ongkir.trips > 0
            ? 'Cucian ' + rupiah(laundryTotal) + ' + ongkir ' + rupiah(ongkir.total)
            : '';
        scheduleQuote();
        document.getElementById('kg-total').textContent = kg > 0 ? 'Total berat ' + kg.toLocaleString('id-ID') + ' kg' : '';
    }

    function reindex() {
        itemsEl.querySelectorAll('.item-row').forEach((row, i) => {
            row.querySelector('.js-service').name = 'items[' + i + '][service_id]';
            row.querySelector('.js-qty').name = 'items[' + i + '][qty]';
        });
    }

    document.getElementById('add-item').addEventListener('click', () => {
        const first = itemsEl.querySelector('.item-row');
        const clone = first.cloneNode(true);
        clone.querySelector('.js-qty').value = '';
        clone.querySelector('.js-min-hint').textContent = '';
        itemsEl.appendChild(clone);
        reindex();
        recalc();
        clone.querySelector('.js-service').focus();
    });

    itemsEl.addEventListener('click', (e) => {
        if (!e.target.classList.contains('js-remove')) return;
        if (itemsEl.querySelectorAll('.item-row').length > 1) {
            e.target.closest('.item-row').remove();
            reindex();
            recalc();
        }
    });

    itemsEl.addEventListener('input', recalc);
    itemsEl.addEventListener('change', recalc);
    document.getElementById('discount').addEventListener('input', recalc);

    const payNow = document.getElementById('pay_now');
    const toggle = () => document.getElementById('pay-method-wrap').style.display = payNow.checked ? '' : 'none';
    payNow.addEventListener('change', toggle);
    toggle();
    recalc();
})();
</script>
@endpush

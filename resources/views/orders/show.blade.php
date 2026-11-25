@extends('layouts.app')
@section('title', 'Order '.$order->code)

@section('content')
<div class="page-head">
    <div>
        <h1>Order {{ $order->code }}</h1>
        <div class="sub">Masuk {{ $order->created_at->translatedFormat('d F Y H:i') }}@if ($order->cashier) oleh {{ $order->cashier->name }}@endif</div>
    </div>
    <div class="row">
        <a href="{{ route('orders.receipt', $order) }}" target="_blank" class="btn btn-outline">Cetak Nota</a>
        <a href="{{ \App\Support\Phone::waLink($order->customer->phone, $order->waMessage()) }}" target="_blank" rel="noopener" class="btn btn-green">Kirim WA</a>
    </div>
</div>

@if ($order->awaitingWeighing())
    <div class="card" style="margin-bottom: 16px; border: 2px solid var(--navy)">
        <h2>Timbang &amp; Isi Layanan</h2>
        <p class="small muted">Isi setelah cucian tiba di outlet dan ditimbang. Total, ongkir, dan promo dihitung otomatis.</p>
        <form method="POST" action="{{ route('orders.items', $order) }}" id="weigh-form">
            @csrf @method('PATCH')
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
                        <div class="line-total js-line">Rp0</div>
                        <div><button type="button" class="btn btn-danger btn-sm js-remove" aria-label="Hapus baris" style="min-height: 44px">×</button></div>
                        <div class="hint js-min-hint" style="grid-column: 1 / -1; margin-top: -4px"></div>
                    </div>
                @endforeach
            </div>
            <div class="row" style="margin-bottom: 12px">
                <button type="button" class="btn btn-outline btn-sm" id="add-item">+ Tambah layanan</button>
            </div>
            @if ($order->pickup)
                @php($pickupDelivery = $order->deliveries()->where('type', 'jemput')->where('status', '!=', 'batal')->latest('id')->first())
                <div class="field">
                    <label for="pickup_staff_id">Petugas yang menjemput</label>
                    <select id="pickup_staff_id" name="pickup_staff_id">
                        <option value="">Kosong / tidak dicatat</option>
                        @foreach ($staff as $st)
                            <option value="{{ $st->id }}" @selected((string) old('pickup_staff_id', $pickupDelivery?->staff_id) === (string) $st->id)>{{ $st->name }}</option>
                        @endforeach
                    </select>
                    <div class="hint">Saat disimpan, jadwal jemput otomatis <strong>Selesai</strong> di daftar Antar-Jemput. Komisi jemput masuk ke petugas ini (jika dipilih).</div>
                </div>
            @endif
            <div class="grid grid-2" style="gap: 12px; align-items: end">
                <div class="field" style="margin: 0">
                    <label for="discount">Diskon (Rp)</label>
                    <input type="number" id="discount" name="discount" min="0" step="500" value="{{ old('discount', 0) }}" inputmode="numeric">
                </div>
                <div class="total-box">
                    <div class="small muted">Total cucian</div>
                    <div class="big" id="grand-total">Rp0</div>
                    @if ($order->delivery_trips > 0)<div class="small muted">+ ongkir {{ rupiah($order->deliveryNet()) }} (promo dihitung ulang saat disimpan)</div>@endif
                </div>
            </div>
            <button type="submit" class="btn btn-block" style="margin-top: 12px">Simpan Berat &amp; Layanan</button>
        </form>
    </div>
@endif

<div class="grid grid-2">
    <div class="stack">
        <div class="card">
            <div class="row" style="margin-bottom: 12px">
                <span class="badge badge-{{ $order->status }}">{{ $order->statusLabel() }}</span>
                <span class="badge badge-{{ $order->payment_status }}">{{ $order->isPaid() ? 'Lunas' : 'Belum dibayar' }}</span>
                @if ($order->stamp_awarded)<span class="badge badge-member">+1 stempel</span>@endif
            </div>

            @if ($next = $order->allowedNextStatuses())
                <form method="POST" action="{{ route('orders.status', $order) }}" class="inline-form" style="margin-bottom: 12px">
                    @csrf @method('PATCH')
                    <label for="status" class="sr-only" style="margin: 0">Ubah status</label>
                    <select id="status" name="status">
                        @foreach ($next as $s)
                            <option value="{{ $s }}">{{ \App\Models\Order::STATUSES[$s] }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn">Ubah Status</button>
                </form>
            @endif

            @unless ($order->isPaid() || $order->status === 'batal' || $order->awaitingWeighing())
                <form method="POST" action="{{ route('orders.pay', $order) }}" class="inline-form">
                    @csrf @method('PATCH')
                    <select name="payment_method" aria-label="Metode bayar">
                        @foreach (\App\Models\Order::PAYMENT_METHODS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-green">Tandai Lunas</button>
                </form>
            @endunless

            @if (($order->delivery_method === 'antar' || $order->pickup) && ! $order->isClosed())
                @php($antarDelivery = $order->deliveries()->where('type', 'antar')->where('status', '!=', 'batal')->with('staff')->first())
                @php($jemputDelivery = $order->deliveries()->where('type', 'jemput')->where('status', '!=', 'batal')->with('staff')->first())
                <div class="fee-box" style="margin-top: 12px">
                    @if ($order->pickup)
                        <div><strong>Jemput:</strong>
                            @if ($jemputDelivery)
                                {{ $jemputDelivery->statusLabel() }} · {{ $jemputDelivery->staff?->name ?? 'belum ada kurir' }}
                            @else
                                belum dijadwalkan
                                <a href="{{ route('deliveries.create', ['order' => $order->id]) }}">Jadwalkan jemput</a>
                            @endif
                        </div>
                    @endif
                    @if ($order->delivery_method === 'antar')
                        <div><strong>Antar:</strong>
                            @if ($antarDelivery)
                                {{ $antarDelivery->statusLabel() }} · {{ $antarDelivery->staff?->name ?? 'belum ada kurir' }}
                                <a href="{{ route('deliveries.index') }}">Atur kurir</a>
                            @elseif ($order->readyForDelivery())
                                siap diantar <a href="{{ route('deliveries.create', ['order' => $order->id]) }}">Buat jadwal antar</a>
                            @else
                                <span class="warn">otomatis masuk daftar pengiriman setelah status <em>Siap</em> dan <em>lunas</em>@unless ($order->isPaid()) (belum lunas)@endunless.</span>
                            @endif
                        </div>
                    @endif
                </div>
                @if ($order->customer->directionsUrl())
                    <div class="row" style="margin-top: 8px">
                        <a href="{{ $order->customer->directionsUrl() }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Rute ke pelanggan</a>
                    </div>
                @endif
            @endif
        </div>

        <div class="card">
            <h2>Rincian</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Layanan</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
                    <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>{{ $item->service_name }}</td>
                            <td class="num">{{ qty($item->qty) }} {{ $item->unit }}
                                @if ($item->usesMinimum())<br><span class="small muted">ditagih min. {{ qty($item->charged_qty) }} {{ $item->unit }}</span>@endif</td>
                            <td class="num">{{ rupiah($item->price) }}</td>
                            <td class="num">{{ rupiah($item->subtotal) }}</td>
                        </tr>
                    @endforeach
                    <tr><td colspan="3" class="num">Subtotal</td><td class="num">{{ rupiah($order->subtotal) }}</td></tr>
                    @if ($order->discount > 0)
                        <tr><td colspan="3" class="num">Diskon</td><td class="num">-{{ rupiah($order->discount) }}</td></tr>
                    @endif
                    @if ($order->delivery_trips > 0)
                        <tr><td colspan="3" class="num">Ongkir {{ strtolower($order->tripsLabel()) }}@if ($order->zone) ({{ $order->zone->name }})@endif</td><td class="num">{{ rupiah($order->delivery_fee) }}</td></tr>
                        @if ($order->delivery_discount > 0)
                            <tr><td colspan="3" class="num">{{ $order->delivery_note }}</td><td class="num">-{{ rupiah($order->delivery_discount) }}</td></tr>
                        @endif
                    @endif
                    <tr><td colspan="3" class="num strong">Total</td><td class="num strong">{{ rupiah($order->total) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <h2>Pelanggan</h2>
            <dl class="info">
                <dt>Nama</dt><dd><a href="{{ route('customers.show', $order->customer) }}">{{ $order->customer->name }}</a>
                    @if ($order->customer->is_member)<span class="badge badge-member">Member</span>@endif</dd>
                <dt>No. HP</dt><dd>{{ \App\Support\Phone::format($order->customer->phone) }}</dd>
                <dt>Alamat</dt><dd>{{ $order->customer->address ?: '-' }}</dd>
                @if ($order->customer->is_member)
                    <dt>Stempel</dt><dd>{{ $order->customer->stamps }} / {{ config('laundry.stamp_target') }}</dd>
                @endif
            </dl>
        </div>

        <div class="card">
            <h2>Petugas</h2>
            @if (! $order->isClosed())
                <form method="POST" action="{{ route('orders.staff', $order) }}" class="inline-form">
                    @csrf @method('PATCH')
                    <select name="staff_id" aria-label="Petugas pengerjaan">
                        <option value="">Belum ditentukan</option>
                        @foreach ($staff as $st)
                            <option value="{{ $st->id }}" @selected($order->staff_id === $st->id)>{{ $st->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline btn-sm">Simpan</button>
                </form>
            @else
                <p>{{ $order->staff?->name ?? 'Tidak ada petugas' }}</p>
            @endif
            @if ($order->staffCommissions->isNotEmpty())
                <div class="table-wrap" style="margin-top: 10px">
                    <table>
                        <tbody>
                        @foreach ($order->staffCommissions as $sc)
                            <tr>
                                <td>{{ $sc->staff->name }}<br><span class="small muted">{{ $sc->typeLabel() }} · {{ qty($sc->qty) }} × {{ rupiah($sc->rate) }}</span></td>
                                <td class="num">{{ rupiah($sc->amount) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif ($order->staff_id)
                <p class="small muted" style="margin-top: 8px">Komisi tercatat saat order selesai dan lunas.</p>
            @endif
        </div>

        <div class="card">
            <h2>Info order</h2>
            <dl class="info">
                <dt>Antar-jemput</dt><dd>{{ $order->delivery_trips > 0 || $order->pickup || $order->delivery_method === 'antar' ? $order->tripsLabel() : 'Tidak (datang & ambil sendiri)' }}@if ($order->distance_km !== null) · {{ qty($order->distance_km) }} km @endif</dd>
                <dt>Estimasi selesai</dt><dd>{{ $order->due_at?->translatedFormat('d M Y H:i') ?? '-' }}</dd>
                @if ($order->completed_at)<dt>Selesai</dt><dd>{{ $order->completed_at->translatedFormat('d M Y H:i') }}</dd>@endif
                @if ($order->paid_at)<dt>Dibayar</dt><dd>{{ $order->paid_at->translatedFormat('d M Y H:i') }} ({{ \App\Models\Order::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method }})</dd>@endif
                <dt>Catatan</dt><dd>{{ $order->notes ?: '-' }}</dd>
                @if ($order->mitra)
                    <dt>Mitra</dt><dd>{{ $order->mitra->name }}</dd>
                    <dt>Komisi mitra</dt><dd>{{ rupiah($order->commission_amount) }}
                        @if ($order->payout) <span class="badge badge-lunas">Sudah dicairkan</span>
                        @elseif ($order->status === 'selesai' && $order->isPaid()) <span class="badge badge-siap">Siap dicairkan</span>
                        @else <span class="badge">Menunggu order selesai &amp; lunas</span>@endif
                    </dd>
                @endif
            </dl>
        </div>
    </div>
</div>
@endsection

@if ($order->awaitingWeighing())
@push('scripts')
<script>
(function () {
    const itemsEl = document.getElementById('items');
    const rupiah = (n) => 'Rp' + Math.round(n).toLocaleString('id-ID');
    const fmt = (n) => n.toLocaleString('id-ID');
    function recalc() {
        let total = 0;
        itemsEl.querySelectorAll('.item-row').forEach((row) => {
            const opt = row.querySelector('.js-service').selectedOptions[0];
            const price = opt ? Number(opt.dataset.price) : 0;
            const unit = opt ? opt.dataset.unit : '';
            const min = opt ? Number(opt.dataset.min) || 0 : 0;
            const q = parseFloat(row.querySelector('.js-qty').value) || 0;
            const charged = q > 0 && min > 0 ? Math.max(q, min) : q;
            row.querySelector('.js-unit').textContent = unit ? '(' + unit + ')' : '';
            row.querySelector('.js-line').textContent = rupiah(price * charged);
            row.querySelector('.js-min-hint').textContent = (q > 0 && charged > q)
                ? 'Di bawah minimum: ditagih ' + fmt(min) + ' ' + unit : '';
            total += Math.round(price * charged);
        });
        const discount = Math.min(Number(document.getElementById('discount').value) || 0, total);
        document.getElementById('grand-total').textContent = rupiah(total - discount);
    }
    function reindex() {
        itemsEl.querySelectorAll('.item-row').forEach((row, i) => {
            row.querySelector('.js-service').name = 'items[' + i + '][service_id]';
            row.querySelector('.js-qty').name = 'items[' + i + '][qty]';
        });
    }
    document.getElementById('add-item').addEventListener('click', () => {
        const clone = itemsEl.querySelector('.item-row').cloneNode(true);
        clone.querySelector('.js-qty').value = '';
        clone.querySelector('.js-min-hint').textContent = '';
        itemsEl.appendChild(clone);
        reindex(); recalc();
    });
    itemsEl.addEventListener('click', (e) => {
        if (e.target.classList.contains('js-remove') && itemsEl.querySelectorAll('.item-row').length > 1) {
            e.target.closest('.item-row').remove(); reindex(); recalc();
        }
    });
    itemsEl.addEventListener('input', recalc);
    itemsEl.addEventListener('change', recalc);
    document.getElementById('discount').addEventListener('input', recalc);
    recalc();
})();
</script>
@endpush
@endif

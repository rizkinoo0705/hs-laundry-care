@extends('layouts.app')
@section('title', 'Titipan Baru')

@section('content')
<div class="page-head">
    <div>
        <h1>Titipan Baru</h1>
        <div class="sub">Timbang cucian pelanggan, lalu isi di sini.</div>
    </div>
</div>

<form method="POST" action="{{ route('mitra.orders.store') }}" class="has-mobile-submit">
    @csrf
    <div class="grid grid-2">
        <div class="card">
            <h2>Pelanggan</h2>
            <div class="field">
                <label for="customer_phone">No. HP / WhatsApp</label>
                <input type="tel" id="customer_phone" name="customer_phone" required value="{{ old('customer_phone') }}" placeholder="08xxxxxxxxxx" autocomplete="off">
            </div>
            <div class="field">
                <label for="customer_name">Nama</label>
                <input type="text" id="customer_name" name="customer_name" required value="{{ old('customer_name') }}">
            </div>
            <div class="field">
                <label for="notes">Catatan</label>
                <textarea id="notes" name="notes" rows="2" placeholder="Mis. ada noda di kemeja putih">{{ old('notes') }}</textarea>
            </div>
        </div>

        <div class="card">
            <h2>Layanan &amp; berat</h2>
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
            <button type="button" class="btn btn-outline btn-sm" id="add-item">+ Tambah layanan</button>

            <div class="total-box" style="margin-top: 14px">
                <div class="small muted">Total</div>
                <div class="big" id="grand-total">Rp0</div>
            </div>
            <button type="submit" class="btn btn-block desktop-submit" style="margin-top: 12px">Simpan Titipan</button>
        </div>
    </div>

    <div class="mobile-submit">
        <div>
            <div class="mt-label">Total</div>
            <div class="mt-total" id="grand-total-mobile">Rp0</div>
        </div>
        <button type="submit" class="btn">Simpan Titipan</button>
    </div>
</form>
@endsection

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
            row.querySelector('.js-min-hint').textContent = (q > 0 && charged > q) ? 'Di bawah minimum: ditagih ' + fmt(min) + ' ' + unit : '';
            total += Math.round(price * charged);
        });
        document.getElementById('grand-total').textContent = rupiah(total);
        document.getElementById('grand-total-mobile').textContent = rupiah(total);
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
    recalc();
})();
</script>
@endpush

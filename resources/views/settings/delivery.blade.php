@extends('layouts.app')
@section('title', 'Zona & Ongkir')
@include('partials.leaflet')

@section('content')
<div class="page-head">
    <div>
        <h1>Zona &amp; Ongkir</h1>
        <div class="sub">Tarif antar-jemput berdasarkan jarak dari outlet.</div>
    </div>
</div>

<form method="POST" action="{{ route('settings.delivery.update') }}">
    @csrf
    @method('PUT')

    <div class="grid grid-2">
        <div class="stack">
            <div class="card">
                <h2>Zona tarif</h2>
                <p class="small muted">Ongkir dihitung <strong>per perjalanan</strong>. Jemput + antar = 2 perjalanan. Zona dipilih dari jarak terkecil yang mencakup jarak pelanggan.</p>

                <div id="zones">
                    @php
                        $rows = old('zones', $zones->map(fn ($z) => [
                            'id' => $z->id, 'name' => $z->name, 'max_km' => qty($z->max_km), 'fee' => $z->fee, 'active' => $z->active,
                        ])->all());
                        if (! $rows) {
                            $rows = [['id' => null, 'name' => 'Zona 1', 'max_km' => '', 'fee' => '', 'active' => true]];
                        }
                    @endphp
                    @foreach ($rows as $i => $row)
                        <div class="zone-row">
                            <input type="hidden" name="zones[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
                            <div>
                                <label for="zone-name-{{ $i }}">Nama zona</label>
                                <input type="text" id="zone-name-{{ $i }}" name="zones[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="Mis. Dekat">
                            </div>
                            <div>
                                <label for="zone-km-{{ $i }}">Sampai (km)</label>
                                <input type="number" id="zone-km-{{ $i }}" name="zones[{{ $i }}][max_km]" step="0.1" min="0.1" value="{{ str_replace(',', '.', $row['max_km'] ?? '') }}" inputmode="decimal">
                            </div>
                            <div>
                                <label for="zone-fee-{{ $i }}">Ongkir (Rp)</label>
                                <input type="number" id="zone-fee-{{ $i }}" name="zones[{{ $i }}][fee]" step="500" min="0" value="{{ $row['fee'] ?? '' }}" inputmode="numeric">
                            </div>
                            <label class="check"><input type="checkbox" name="zones[{{ $i }}][active]" value="1" @checked(! empty($row['active']))> Aktif</label>
                            @if (! empty($row['id']))
                                <label class="check"><input type="checkbox" name="zones[{{ $i }}][delete]" value="1"> Hapus</label>
                            @else
                                <span></span>
                            @endif
                        </div>
                    @endforeach
                </div>
                <button type="button" class="btn btn-outline btn-sm" id="add-zone">+ Tambah zona</button>
                <p class="hint" style="margin-top: 10px">Contoh: Dekat sampai 2 km Rp5.000 · Sedang sampai 5 km Rp10.000 · Jauh sampai 8 km Rp15.000. Pelanggan di atas zona terjauh dianggap di luar zona (ongkir diisi manual).</p>
            </div>

            <div class="card">
                <h2>Gratis ongkir otomatis</h2>
                <div class="field">
                    <label for="free_delivery_min_total">Gratis ongkir jika total cucian minimal (Rp)</label>
                    <input type="number" id="free_delivery_min_total" name="free_delivery_min_total" min="0" step="1000" value="{{ old('free_delivery_min_total', $freeMinTotal ?: '') }}" placeholder="Kosongkan = tidak berlaku" inputmode="numeric">
                </div>
                <label class="check"><input type="checkbox" name="free_delivery_members" value="1" @checked(old('free_delivery_members', $freeMembers))> Gratis ongkir untuk semua member</label>
                <p class="hint">Promo untuk pelanggan tertentu diatur di halaman masing-masing pelanggan (Ubah → Promo ongkir khusus). Jika beberapa promo berlaku, dipakai potongan terbesar.</p>

                @if ($promoCustomers->isNotEmpty())
                    <h3 style="margin-top: 14px">Pelanggan dengan promo khusus</h3>
                    @foreach ($promoCustomers as $c)
                        <p class="small"><a href="{{ route('customers.show', $c) }}">{{ $c->name }}</a> · {{ $c->deliveryPromoLabel() ?? 'promo sudah berakhir' }}</p>
                    @endforeach
                @endif
            </div>
        </div>

        <div class="card">
            <h2>Lokasi outlet</h2>
            <p class="small muted">Titik awal untuk menghitung jarak ke pelanggan. Ketuk peta, cari alamat, atau pakai GPS saat berada di outlet.</p>
            <div class="map-tools">
                <input type="search" id="map-search" placeholder="Cari alamat outlet">
                <button type="button" class="btn btn-outline" id="map-search-btn">Cari</button>
                <button type="button" class="btn btn-outline" id="map-locate">Lokasi saya</button>
            </div>
            <div id="outlet-map" class="map-box"></div>
            <div class="distance-label" id="outlet-label">{{ $outlet ? 'Lokasi outlet tersimpan.' : 'Lokasi outlet belum diatur.' }}</div>
            <input type="hidden" name="outlet_lat" id="outlet_lat" value="{{ old('outlet_lat', $outlet[0] ?? '') }}">
            <input type="hidden" name="outlet_lng" id="outlet_lng" value="{{ old('outlet_lng', $outlet[1] ?? '') }}">
            <p class="hint">Jika lokasi outlet diubah, jarak semua pelanggan dihitung ulang (garis lurus). Buka &amp; simpan ulang pelanggan untuk mendapat jarak lewat jalan.</p>
        </div>
    </div>

    <div class="row" style="margin-top: 16px">
        <button type="submit" class="btn">Simpan Pengaturan</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    HSMapPicker({
        mapId: 'outlet-map', latId: 'outlet_lat', lngId: 'outlet_lng', labelId: 'outlet-label',
        locateId: 'map-locate', searchId: 'map-search', searchBtnId: 'map-search-btn',
        outlet: null, noOutletText: 'Titik outlet dipilih. Klik Simpan Pengaturan.',
    });

    const zones = document.getElementById('zones');
    document.getElementById('add-zone').addEventListener('click', () => {
        const i = zones.querySelectorAll('.zone-row').length;
        const div = document.createElement('div');
        div.className = 'zone-row';
        div.innerHTML =
            '<input type="hidden" name="zones[' + i + '][id]" value="">' +
            '<div><label for="zone-name-' + i + '">Nama zona</label><input type="text" id="zone-name-' + i + '" name="zones[' + i + '][name]" placeholder="Mis. Jauh"></div>' +
            '<div><label for="zone-km-' + i + '">Sampai (km)</label><input type="number" id="zone-km-' + i + '" name="zones[' + i + '][max_km]" step="0.1" min="0.1" inputmode="decimal"></div>' +
            '<div><label for="zone-fee-' + i + '">Ongkir (Rp)</label><input type="number" id="zone-fee-' + i + '" name="zones[' + i + '][fee]" step="500" min="0" inputmode="numeric"></div>' +
            '<label class="check"><input type="checkbox" name="zones[' + i + '][active]" value="1" checked> Aktif</label><span></span>';
        zones.appendChild(div);
        div.querySelector('input[type=text]').focus();
    });
})();
</script>
@endpush

@extends('layouts.app')
@section('title', $customer->exists ? 'Ubah Pelanggan' : 'Pelanggan Baru')
@include('partials.leaflet')

@section('content')
<div class="page-head">
    <h1>{{ $customer->exists ? 'Ubah Pelanggan' : 'Pelanggan Baru' }}</h1>
</div>

<form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}">
    @csrf
    @if ($customer->exists) @method('PUT') @endif

    <div class="grid grid-2">
        <div class="stack">
            <div class="card">
                <h2>Data pelanggan</h2>
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
            </div>

            <div class="card">
                <h2>Promo ongkir khusus</h2>
                @php($promo = old('delivery_promo', $customer->delivery_promo ?? 'none'))
                <div class="field">
                    <label for="delivery_promo">Jenis promo</label>
                    <select id="delivery_promo" name="delivery_promo">
                        @foreach (\App\Models\Customer::DELIVERY_PROMOS as $key => $label)
                            <option value="{{ $key }}" @selected($promo === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-2" style="gap: 12px">
                    <div class="field" id="promo-value-wrap">
                        <label for="delivery_promo_value">Diskon (%)</label>
                        <input type="number" id="delivery_promo_value" name="delivery_promo_value" min="1" max="100" value="{{ old('delivery_promo_value', $customer->delivery_promo_value ?: '') }}" inputmode="numeric">
                    </div>
                    <div class="field" id="promo-until-wrap">
                        <label for="delivery_promo_until">Berlaku sampai</label>
                        <input type="date" id="delivery_promo_until" name="delivery_promo_until" value="{{ old('delivery_promo_until', $customer->delivery_promo_until?->toDateString()) }}">
                        <div class="hint">Kosongkan jika berlaku terus.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Lokasi rumah</h2>
            <p class="small muted">Ketuk peta, geser penanda, cari alamat, atau pakai GPS saat berada di rumah pelanggan.</p>
            <div class="map-tools">
                <input type="search" id="map-search" placeholder="Cari jalan / kelurahan">
                <button type="button" class="btn btn-outline" id="map-search-btn">Cari</button>
                <button type="button" class="btn btn-outline" id="map-locate">Lokasi saya</button>
            </div>
            <div id="customer-map" class="map-box"></div>
            <div class="row">
                <span class="distance-label" id="distance-label">
                    @if ($customer->distance_km !== null)
                        {{ qty($customer->distance_km) }} km {{ $customer->distance_source === 'jalan' ? 'lewat jalan' : '(garis lurus)' }} dari outlet
                    @elseif (! $outlet)
                        Lokasi outlet belum diatur di menu Zona &amp; Ongkir.
                    @else
                        Belum ada titik lokasi.
                    @endif
                </span>
                <span class="spacer"></span>
                <button type="button" class="btn btn-danger btn-sm" id="map-clear">Hapus titik</button>
            </div>
            <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude', $customer->latitude) }}">
            <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', $customer->longitude) }}">
            <input type="hidden" name="road_distance_km" id="road_distance_km" value="{{ old('road_distance_km', $customer->distance_source === 'jalan' ? $customer->distance_km : '') }}">
        </div>
    </div>

    <div class="row" style="margin-top: 16px">
        <button type="submit" class="btn">Simpan</button>
        <a href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}" class="btn btn-outline">Batal</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    HSMapPicker({
        mapId: 'customer-map', latId: 'latitude', lngId: 'longitude',
        distanceId: 'road_distance_km', labelId: 'distance-label',
        locateId: 'map-locate', searchId: 'map-search', searchBtnId: 'map-search-btn', clearId: 'map-clear',
        outlet: @json($outlet),
        noOutletText: 'Titik tersimpan. Atur lokasi outlet di menu Zona & Ongkir agar jarak terhitung.',
    });

    const promo = document.getElementById('delivery_promo');
    const toggle = () => {
        document.getElementById('promo-value-wrap').style.display = promo.value === 'percent' ? '' : 'none';
        document.getElementById('promo-until-wrap').style.display = promo.value === 'none' ? 'none' : '';
    };
    promo.addEventListener('change', toggle);
    toggle();
})();
</script>
@endpush

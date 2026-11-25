@extends('layouts.app')
@section('title', 'Antar-Jemput')

@section('content')
<div class="page-head">
    <div>
        <h1>Antar-Jemput</h1>
        <div class="sub">{{ $date->translatedFormat('l, d F Y') }}</div>
    </div>
    <a href="{{ route('deliveries.create') }}" class="btn">+ Jadwal</a>
</div>

@if ($needCourier->isNotEmpty())
    <div class="card" style="margin-bottom: 16px; border: 2px solid var(--amber)">
        <h2>Perlu kurir ({{ $needCourier->count() }})</h2>
        <p class="small muted">Pengiriman yang belum ada kurirnya. Order antar yang sudah <strong>siap dan lunas</strong> otomatis masuk ke sini.</p>
        <div class="table-wrap">
            <table class="rtable">
                <thead><tr><th>Masuk</th><th>Jenis</th><th>Tujuan</th><th>Order</th><th>Status</th><th>Ubah</th></tr></thead>
                <tbody>
                @foreach ($needCourier as $delivery)
                    @include('deliveries._row', ['delivery' => $delivery, 'showDate' => true])
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="card">
    <form method="GET" class="filters">
        <div>
            <label for="date">Tanggal</label>
            <input type="date" id="date" name="date" value="{{ $date->toDateString() }}">
        </div>
        <div>
            <label for="type">Jenis</label>
            <select id="type" name="type">
                <option value="">Semua</option>
                @foreach (\App\Models\Delivery::TYPES as $key => $label)
                    <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-outline">Tampilkan</button>
    </form>

    <div class="table-wrap">
        <table class="rtable">
            <thead><tr><th>Jam</th><th>Jenis</th><th>Tujuan</th><th>Order</th><th>Status</th><th>Ubah</th></tr></thead>
            <tbody>
            @forelse ($deliveries as $delivery)
                @include('deliveries._row', ['delivery' => $delivery])
            @empty
                <tr><td colspan="6" class="empty">Tidak ada jadwal di tanggal ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

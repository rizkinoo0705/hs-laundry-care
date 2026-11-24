@extends('layouts.app')
@section('title', 'Petugas & Komisi')

@section('content')
<div class="page-head">
    <div>
        <h1>Petugas &amp; Komisi</h1>
        <div class="sub">Komisi pengerjaan per kg/pcs (diatur di Layanan &amp; Harga) dan per perjalanan jemput/antar.</div>
    </div>
    <a href="{{ route('staff.create') }}" class="btn">+ Petugas</a>
</div>

<div class="grid grid-2" style="margin-bottom: 16px">
    <div class="card">
        <h2>Tarif komisi jemput/antar</h2>
        <form method="POST" action="{{ route('staff.rates') }}">
            @csrf @method('PUT')
            <div class="grid grid-2" style="gap: 12px">
                <div class="field">
                    <label for="staff_rate_jemput">Per jemput (Rp)</label>
                    <input type="number" id="staff_rate_jemput" name="staff_rate_jemput" min="0" step="500" value="{{ old('staff_rate_jemput', $rateJemput ?: '') }}" inputmode="numeric" placeholder="Mis. 1000">
                </div>
                <div class="field">
                    <label for="staff_rate_antar">Per antar (Rp)</label>
                    <input type="number" id="staff_rate_antar" name="staff_rate_antar" min="0" step="500" value="{{ old('staff_rate_antar', $rateAntar ?: '') }}" inputmode="numeric" placeholder="Mis. 1000">
                </div>
            </div>
            <button type="submit" class="btn btn-outline">Simpan Tarif</button>
        </form>
    </div>
    <div class="card">
        <h2>Cara kerja</h2>
        <p class="small">1. Pilih <strong>petugas pengerjaan</strong> di form order. Komisinya tercatat saat order <strong>selesai dan lunas</strong>: berat/jumlah yang ditagih × komisi layanan.</p>
        <p class="small">2. Pilih <strong>kurir</strong> di jadwal antar-jemput. Komisinya tercatat saat jadwal ditandai <strong>selesai</strong>.</p>
        <p class="small">3. Buka petugas → <strong>Catat Sudah Dibayar</strong> saat komisi diberikan.</p>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="rtable">
            <thead><tr><th>Petugas</th><th>Posisi</th><th class="num">Komisi bulan ini</th><th class="num">Belum dibayar</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($staff as $member)
                <tr>
                    <td data-label="Petugas"><a href="{{ route('staff.show', $member) }}" class="strong">{{ $member->name }}</a>
                        @if ($member->phone)<br><span class="small muted">{{ \App\Support\Phone::format($member->phone) }}</span>@endif</td>
                    <td data-label="Posisi">{{ $member->positionLabel() }}</td>
                    <td data-label="Komisi bulan ini" class="num">{{ rupiah($member->month_total ?? 0) }}</td>
                    <td data-label="Belum dibayar" class="num strong">{{ rupiah($member->unpaid ?? 0) }}</td>
                    <td data-label="Status">@if ($member->active)<span class="badge badge-selesai">Aktif</span>@else<span class="badge">Nonaktif</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Belum ada petugas. Tambahkan petugas terlebih dahulu.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

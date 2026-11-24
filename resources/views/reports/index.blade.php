@extends('layouts.app')
@section('title', 'Laporan')

@section('content')
<div class="page-head">
    <div>
        <h1>Laporan</h1>
        <div class="sub">{{ $from->translatedFormat('d M Y') }} – {{ $to->translatedFormat('d M Y') }}</div>
    </div>
</div>

<div class="card" style="margin-bottom: 16px">
    <form method="GET" class="filters" style="margin-bottom: 0">
        <div>
            <label for="from">Dari</label>
            <input type="date" id="from" name="from" value="{{ $from->toDateString() }}">
        </div>
        <div>
            <label for="to">Sampai</label>
            <input type="date" id="to" name="to" value="{{ $to->toDateString() }}">
        </div>
        <button type="submit" class="btn btn-outline">Tampilkan</button>
        <a href="{{ route('reports.index', ['from' => now()->toDateString(), 'to' => now()->toDateString()]) }}" class="btn btn-outline">Hari ini</a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline">Bulan ini</a>
    </form>
</div>

<div class="grid grid-4" style="margin-bottom: 16px">
    <div class="stat">
        <div class="stat-label">Omzet (termasuk ongkir)</div>
        <div class="stat-value">{{ rupiah($summary['omzet']) }}</div>
        <div class="stat-note">{{ $summary['orders'] }} order · {{ qty($summary['kg']) }} kg</div>
    </div>
    <div class="stat">
        <div class="stat-label">Uang masuk (dilunasi)</div>
        <div class="stat-value">{{ rupiah($summary['income']) }}</div>
        <div class="stat-note">berdasarkan tanggal bayar</div>
    </div>
    <div class="stat">
        <div class="stat-label">Belum dibayar</div>
        <div class="stat-value">{{ rupiah($summary['unpaid']) }}</div>
        <div class="stat-note">diskon diberikan {{ rupiah($summary['discount']) }}</div>
    </div>
    <div class="stat">
        <div class="stat-label">Ongkir antar-jemput</div>
        <div class="stat-value">{{ rupiah($summary['delivery_fee'] - $summary['delivery_discount']) }}</div>
        <div class="stat-note">{{ $summary['delivery_trips'] }} perjalanan · promo {{ rupiah($summary['delivery_discount']) }}</div>
    </div>
    <div class="stat">
        <div class="stat-label">Komisi mitra</div>
        <div class="stat-value">{{ rupiah($summary['commission']) }}</div>
        <div class="stat-note">dari order mitra yang selesai</div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Per hari</h2>
        <div class="table-wrap">
            <table class="rtable">
                <thead><tr><th>Tanggal</th><th class="num">Order</th><th class="num">Kg</th><th class="num">Omzet</th><th class="num">Uang masuk</th></tr></thead>
                <tbody>
                @forelse ($daily as $day => $row)
                    <tr>
                        <td data-label="Tanggal" class="nowrap">{{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('D, d M') }}</td>
                        <td data-label="Order" class="num">{{ $row['orders'] }}</td>
                        <td data-label="Kg" class="num">{{ qty($row['kg']) }}</td>
                        <td data-label="Omzet" class="num">{{ rupiah($row['omzet']) }}</td>
                        <td data-label="Uang masuk" class="num">{{ rupiah($row['income']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Tidak ada data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <h2>Per layanan</h2>
            <div class="table-wrap">
                <table class="rtable">
                    <thead><tr><th>Layanan</th><th class="num">Jumlah</th><th class="num">Total</th></tr></thead>
                    <tbody>
                    @forelse ($byService as $row)
                        <tr>
                            <td data-label="Layanan">{{ $row->service_name }}</td>
                            <td data-label="Jumlah" class="num">{{ qty($row->qty) }} {{ $row->unit }}</td>
                            <td data-label="Total" class="num">{{ rupiah($row->total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">Tidak ada data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Per mitra dropbox</h2>
            <div class="table-wrap">
                <table class="rtable">
                    <thead><tr><th>Mitra</th><th class="num">Order</th><th class="num">Kg</th><th class="num">Omzet</th><th class="num">Komisi</th></tr></thead>
                    <tbody>
                    @forelse ($byMitra as $row)
                        <tr>
                            <td data-label="Mitra"><a href="{{ route('mitras.show', $row['mitra']) }}">{{ $row['mitra']->name }}</a></td>
                            <td data-label="Order" class="num">{{ $row['orders'] }}</td>
                            <td data-label="Kg" class="num">{{ qty($row['kg']) }}</td>
                            <td data-label="Omzet" class="num">{{ rupiah($row['omzet']) }}</td>
                            <td data-label="Komisi" class="num">{{ rupiah($row['commission']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">Belum ada order lewat mitra.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

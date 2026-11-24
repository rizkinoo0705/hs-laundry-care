@extends('layouts.app')
@section('title', $member->name)

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $member->name }}</h1>
        <div class="sub">{{ $member->positionLabel() }}@if ($member->phone) · {{ \App\Support\Phone::format($member->phone) }}@endif</div>
    </div>
    <div class="row">
        @if ($member->phone)
            <a href="{{ \App\Support\Phone::waLink($member->phone) }}" target="_blank" rel="noopener" class="btn btn-green">WhatsApp</a>
        @endif
        <a href="{{ route('staff.edit', $member) }}" class="btn btn-outline">Ubah</a>
    </div>
</div>

<div class="grid grid-2">
    <div class="stack">
        <div class="card">
            <h2>Komisi belum dibayar</h2>
            @php($unpaidTotal = $unpaid->sum('amount'))
            <div class="stat-value" style="margin-bottom: 6px">{{ rupiah($unpaidTotal) }}</div>
            <p class="small muted">{{ $unpaid->count() }} pekerjaan.</p>
            @if ($unpaid->isNotEmpty())
                <form method="POST" action="{{ route('staff.payout', $member) }}" onsubmit="return confirm('Catat komisi {{ rupiah($unpaidTotal) }} sudah dibayar ke {{ $member->name }}?')">
                    @csrf
                    <div class="field">
                        <label for="notes">Catatan pembayaran</label>
                        <input type="text" id="notes" name="notes" placeholder="Mis. tunai / transfer, periode minggu ini">
                    </div>
                    <button type="submit" class="btn btn-green">Catat Sudah Dibayar</button>
                </form>
                <div class="table-wrap" style="margin-top: 12px">
                    <table class="rtable">
                        <thead><tr><th>Pekerjaan</th><th>Tanggal</th><th class="num">Hitungan</th><th class="num">Komisi</th></tr></thead>
                        <tbody>
                        @foreach ($unpaid as $row)
                            <tr>
                                <td data-label="Pekerjaan"><span class="badge">{{ $row->typeLabel() }}</span> {{ $row->description }}</td>
                                <td data-label="Tanggal" class="small nowrap">{{ $row->earned_at->translatedFormat('d M H:i') }}</td>
                                <td data-label="Hitungan" class="num">{{ qty($row->qty) }} × {{ rupiah($row->rate) }}</td>
                                <td data-label="Komisi" class="num strong">{{ rupiah($row->amount) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card">
            <h2>Riwayat pembayaran komisi</h2>
            <div class="table-wrap">
                <table>
                    <tbody>
                    @forelse ($payouts as $payout)
                        <tr>
                            <td class="small nowrap">{{ $payout->paid_at->translatedFormat('d M Y') }}</td>
                            <td>{{ $payout->items_count }} pekerjaan<br><span class="small muted">{{ $payout->notes }}</span></td>
                            <td class="num strong">{{ rupiah($payout->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td class="empty">Belum ada pembayaran.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Rekap periode</h2>
        <form method="GET" class="filters">
            <div>
                <label for="from">Dari</label>
                <input type="date" id="from" name="from" value="{{ $from->toDateString() }}">
            </div>
            <div>
                <label for="to">Sampai</label>
                <input type="date" id="to" name="to" value="{{ $to->toDateString() }}">
            </div>
            <button type="submit" class="btn btn-outline">Tampilkan</button>
        </form>

        <div class="grid grid-3" style="gap: 10px; margin-bottom: 12px">
            <div class="stat"><div class="stat-label">Pengerjaan</div><div class="stat-value">{{ rupiah($period->where('type', 'layanan')->sum('amount')) }}</div><div class="stat-note">{{ qty($period->where('type', 'layanan')->sum('qty')) }} kg/pcs</div></div>
            <div class="stat"><div class="stat-label">Jemput/antar</div><div class="stat-value">{{ rupiah($period->whereIn('type', ['jemput', 'antar'])->sum('amount')) }}</div><div class="stat-note">{{ $period->whereIn('type', ['jemput', 'antar'])->count() }} perjalanan</div></div>
            <div class="stat"><div class="stat-label">Total</div><div class="stat-value">{{ rupiah($period->sum('amount')) }}</div></div>
        </div>

        <div class="table-wrap">
            <table class="rtable">
                <thead><tr><th>Pekerjaan</th><th>Tanggal</th><th class="num">Komisi</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($period as $row)
                    <tr>
                        <td data-label="Pekerjaan"><span class="badge">{{ $row->typeLabel() }}</span> {{ $row->description }}</td>
                        <td data-label="Tanggal" class="small nowrap">{{ $row->earned_at->translatedFormat('d M H:i') }}</td>
                        <td data-label="Komisi" class="num">{{ rupiah($row->amount) }}</td>
                        <td data-label="Status">@if ($row->staff_payout_id)<span class="badge badge-lunas">Dibayar</span>@else<span class="badge badge-siap">Belum</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Tidak ada komisi di periode ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Portal Mitra')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $mitra->name }}</h1>
        <div class="sub">Mitra Dropbox · Komisi {{ $mitra->commissionLabel() }}</div>
    </div>
    <a href="{{ \App\Support\Phone::waLink(config('laundry.wa_number')) }}" target="_blank" rel="noopener" class="btn btn-green">Hubungi HS Laundry</a>
</div>

<div class="grid grid-4" style="margin-bottom: 16px">
    <div class="stat">
        <div class="stat-label">Order bulan ini</div>
        <div class="stat-value">{{ $stats['orders_month'] }}</div>
        <div class="stat-note">{{ qty($stats['kg_month']) }} kg</div>
    </div>
    <div class="stat">
        <div class="stat-label">Komisi bulan ini</div>
        <div class="stat-value">{{ rupiah($stats['commission_month']) }}</div>
        <div class="stat-note">dari order yang sudah selesai</div>
    </div>
    <div class="stat">
        <div class="stat-label">Komisi belum dicairkan</div>
        <div class="stat-value">{{ rupiah($stats['unpaid']) }}</div>
        <div class="stat-note">order selesai &amp; lunas</div>
    </div>
    <div class="stat">
        <div class="stat-label">Masih diproses</div>
        <div class="stat-value">{{ $stats['in_progress'] }}</div>
        <div class="stat-note">cucian</div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Cucian lewat outlet Anda</h2>
        <div class="table-wrap">
            <table class="rtable">
                <thead><tr><th>Order</th><th>Status</th><th class="num">Berat</th><th class="num">Komisi</th></tr></thead>
                <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td data-label="Order" class="strong">{{ $order->code }}<br><span class="small muted">{{ $order->customer->name }} · {{ $order->created_at->translatedFormat('d M') }}</span></td>
                        <td data-label="Status"><span class="badge badge-{{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                        <td data-label="Berat" class="num">{{ $order->total_kg > 0 ? qty($order->total_kg).' kg' : '-' }}</td>
                        <td data-label="Komisi" class="num">{{ rupiah($order->commission_amount) }}
                            @if ($order->mitra_payout_id)<br><span class="badge badge-lunas">Cair</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Belum ada cucian.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    </div>

    <div class="card">
        <h2>Riwayat pencairan komisi</h2>
        <div class="table-wrap">
            <table>
                <tbody>
                @forelse ($payouts as $payout)
                    <tr>
                        <td class="small nowrap">{{ $payout->paid_at->translatedFormat('d M Y') }}</td>
                        <td>{{ $payout->orders_count }} order · {{ qty($payout->total_kg) }} kg</td>
                        <td class="num strong">{{ rupiah($payout->amount) }}</td>
                    </tr>
                @empty
                    <tr><td class="empty">Belum ada pencairan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

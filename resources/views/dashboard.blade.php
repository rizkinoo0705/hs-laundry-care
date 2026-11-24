@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <div class="sub">{{ now()->translatedFormat('l, d F Y') }}</div>
    </div>
    <a href="{{ route('orders.create') }}" class="btn">+ Order Baru</a>
</div>

<div class="grid grid-4" style="margin-bottom: 16px">
    <div class="stat">
        <div class="stat-label">Order hari ini</div>
        <div class="stat-value">{{ $ordersToday }}</div>
        <div class="stat-note">{{ qty($kgToday) }} kg</div>
    </div>
    <div class="stat">
        <div class="stat-label">Uang masuk hari ini</div>
        <div class="stat-value">{{ rupiah($incomeToday) }}</div>
        <div class="stat-note">dari order yang dilunasi hari ini</div>
    </div>
    <div class="stat">
        <div class="stat-label">Belum dibayar</div>
        <div class="stat-value">{{ rupiah($unpaidTotal) }}</div>
        <div class="stat-note"><a href="{{ route('orders.index', ['payment' => 'belum']) }}">Lihat order</a></div>
    </div>
    <div class="stat">
        <div class="stat-label">Member aktif</div>
        <div class="stat-value">{{ $memberCount }}</div>
        <div class="stat-note"><a href="{{ route('customers.index', ['member' => 1]) }}">Lihat member</a></div>
    </div>
</div>

<div class="card" style="margin-bottom: 16px">
    <h2>Cucian dalam proses</h2>
    <div class="row">
        @foreach (['diterima', 'dicuci', 'disetrika', 'siap'] as $status)
            <a href="{{ route('orders.index', ['status' => $status]) }}" class="btn btn-outline">
                {{ \App\Models\Order::STATUSES[$status] }}: {{ $statusCounts[$status] ?? 0 }}
            </a>
        @endforeach
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Siap diambil / diantar</h2>
        <div class="table-wrap">
            <table>
                <tbody>
                @forelse ($readyOrders as $order)
                    <tr>
                        <td><a href="{{ route('orders.show', $order) }}">{{ $order->code }}</a><br><span class="small muted">{{ $order->customer->name }}</span></td>
                        <td class="num">{{ rupiah($order->total) }}<br><span class="badge badge-{{ $order->payment_status }}">{{ $order->isPaid() ? 'Lunas' : 'Belum bayar' }}</span></td>
                    </tr>
                @empty
                    <tr><td class="empty">Belum ada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Terlambat dari estimasi</h2>
        <div class="table-wrap">
            <table>
                <tbody>
                @forelse ($overdueOrders as $order)
                    <tr>
                        <td><a href="{{ route('orders.show', $order) }}">{{ $order->code }}</a><br><span class="small muted">{{ $order->customer->name }}</span></td>
                        <td><span class="badge badge-{{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                        <td class="num small">{{ $order->due_at->translatedFormat('d M H:i') }}</td>
                    </tr>
                @empty
                    <tr><td class="empty">Tidak ada yang terlambat.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="grid-column: 1 / -1">
        <div class="row" style="margin-bottom: 8px">
            <h2 style="margin: 0">Antar-jemput hari ini</h2>
            <span class="spacer"></span>
            <a href="{{ route('deliveries.create') }}" class="btn btn-outline btn-sm">+ Jadwal</a>
        </div>
        <div class="table-wrap">
            <table>
                <tbody>
                @forelse ($deliveriesToday as $delivery)
                    <tr>
                        <td class="nowrap">{{ $delivery->scheduled_at->format('H:i') }}</td>
                        <td><span class="badge">{{ $delivery->typeLabel() }}</span></td>
                        <td>{{ $delivery->targetName() }}<br><span class="small muted">{{ $delivery->address }}</span></td>
                        <td><span class="badge badge-{{ $delivery->status }}">{{ $delivery->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td class="empty">Tidak ada jadwal hari ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

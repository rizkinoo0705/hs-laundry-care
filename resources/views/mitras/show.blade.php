@extends('layouts.app')
@section('title', $mitra->name)

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $mitra->name }}</h1>
        <div class="sub">{{ $mitra->owner_name }} · {{ \App\Support\Phone::format($mitra->phone) }} · Komisi {{ $mitra->commissionLabel() }}</div>
    </div>
    <div class="row">
        @if ($mitra->phone)
            <a href="{{ \App\Support\Phone::waLink($mitra->phone) }}" target="_blank" rel="noopener" class="btn btn-green">WhatsApp</a>
        @endif
        <a href="{{ route('mitras.edit', $mitra) }}" class="btn btn-outline">Ubah</a>
    </div>
</div>

<div class="grid grid-2">
    <div class="stack">
        <div class="card">
            <h2>Komisi siap dicairkan</h2>
            @php($pendingTotal = $pendingOrders->sum('commission_amount'))
            <div class="stat-value" style="margin-bottom: 6px">{{ rupiah($pendingTotal) }}</div>
            <p class="small muted">{{ $pendingOrders->count() }} order selesai &amp; lunas, {{ qty($pendingOrders->sum('total_kg')) }} kg.</p>
            @if ($pendingOrders->isNotEmpty())
                <form method="POST" action="{{ route('mitras.payout', $mitra) }}" onsubmit="return confirm('Catat komisi {{ rupiah($pendingTotal) }} sudah dibayar ke mitra?')">
                    @csrf
                    <div class="field">
                        <label for="notes">Catatan pembayaran</label>
                        <input type="text" id="notes" name="notes" placeholder="Mis. transfer BCA / tunai">
                    </div>
                    <button type="submit" class="btn btn-green">Catat Sudah Dibayar</button>
                </form>
                <div class="table-wrap" style="margin-top: 12px">
                    <table>
                        <tbody>
                        @foreach ($pendingOrders as $order)
                            <tr>
                                <td><a href="{{ route('orders.show', $order) }}">{{ $order->code }}</a></td>
                                <td>{{ $order->customer->name }}</td>
                                <td class="num">{{ qty($order->total_kg) }} kg</td>
                                <td class="num">{{ rupiah($order->commission_amount) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card">
            <h2>Riwayat pencairan</h2>
            <div class="table-wrap">
                <table>
                    <tbody>
                    @forelse ($payouts as $payout)
                        <tr>
                            <td class="small nowrap">{{ $payout->paid_at->translatedFormat('d M Y') }}</td>
                            <td>{{ $payout->orders_count }} order · {{ qty($payout->total_kg) }} kg<br><span class="small muted">{{ $payout->notes }}</span></td>
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

    <div class="stack">
        <div class="card">
            <h2>Order terakhir</h2>
            <div class="table-wrap">
                <table>
                    <tbody>
                    @forelse ($recentOrders as $order)
                        <tr>
                            <td><a href="{{ route('orders.show', $order) }}">{{ $order->code }}</a><br><span class="small muted">{{ $order->customer->name }}</span></td>
                            <td><span class="badge badge-{{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                            <td class="num">{{ rupiah($order->commission_amount) }}</td>
                        </tr>
                    @empty
                        <tr><td class="empty">Belum ada order.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Akun login mitra</h2>
            @forelse ($users as $u)
                <p>{{ $u->name }} · {{ $u->email }} @unless ($u->active)<span class="badge">Nonaktif</span>@endunless</p>
            @empty
                <p class="muted small">Belum ada akun. Buat di menu <a href="{{ route('users.create') }}">Pengguna</a> dengan peran "Mitra Dropbox".</p>
            @endforelse
        </div>
    </div>
</div>
@endsection

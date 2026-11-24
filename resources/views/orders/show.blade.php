@extends('layouts.app')
@section('title', 'Order '.$order->code)

@section('content')
<div class="page-head">
    <div>
        <h1>Order {{ $order->code }}</h1>
        <div class="sub">Masuk {{ $order->created_at->translatedFormat('d F Y H:i') }}@if ($order->cashier) oleh {{ $order->cashier->name }}@endif</div>
    </div>
    <div class="row">
        <a href="{{ route('orders.receipt', $order) }}" target="_blank" class="btn btn-outline">Cetak Nota</a>
        <a href="{{ \App\Support\Phone::waLink($order->customer->phone, $order->waMessage()) }}" target="_blank" rel="noopener" class="btn btn-green">Kirim WA</a>
    </div>
</div>

<div class="grid grid-2">
    <div class="stack">
        <div class="card">
            <div class="row" style="margin-bottom: 12px">
                <span class="badge badge-{{ $order->status }}">{{ $order->statusLabel() }}</span>
                <span class="badge badge-{{ $order->payment_status }}">{{ $order->isPaid() ? 'Lunas' : 'Belum dibayar' }}</span>
                @if ($order->stamp_awarded)<span class="badge badge-member">+1 stempel</span>@endif
            </div>

            @if ($next = $order->allowedNextStatuses())
                <form method="POST" action="{{ route('orders.status', $order) }}" class="inline-form" style="margin-bottom: 12px">
                    @csrf @method('PATCH')
                    <label for="status" class="sr-only" style="margin: 0">Ubah status</label>
                    <select id="status" name="status">
                        @foreach ($next as $s)
                            <option value="{{ $s }}">{{ \App\Models\Order::STATUSES[$s] }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn">Ubah Status</button>
                </form>
            @endif

            @unless ($order->isPaid() || $order->status === 'batal')
                <form method="POST" action="{{ route('orders.pay', $order) }}" class="inline-form">
                    @csrf @method('PATCH')
                    <select name="payment_method" aria-label="Metode bayar">
                        @foreach (\App\Models\Order::PAYMENT_METHODS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-green">Tandai Lunas</button>
                </form>
            @endunless

            @if ($order->delivery_method === 'antar' && ! $order->isClosed())
                <p style="margin-top: 12px"><a href="{{ route('deliveries.create', ['order' => $order->id]) }}" class="btn btn-outline btn-sm">+ Jadwalkan antar</a></p>
            @endif
        </div>

        <div class="card">
            <h2>Rincian</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Layanan</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
                    <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>{{ $item->service_name }}</td>
                            <td class="num">{{ qty($item->qty) }} {{ $item->unit }}</td>
                            <td class="num">{{ rupiah($item->price) }}</td>
                            <td class="num">{{ rupiah($item->subtotal) }}</td>
                        </tr>
                    @endforeach
                    <tr><td colspan="3" class="num">Subtotal</td><td class="num">{{ rupiah($order->subtotal) }}</td></tr>
                    @if ($order->discount > 0)
                        <tr><td colspan="3" class="num">Diskon</td><td class="num">-{{ rupiah($order->discount) }}</td></tr>
                    @endif
                    <tr><td colspan="3" class="num strong">Total</td><td class="num strong">{{ rupiah($order->total) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <h2>Pelanggan</h2>
            <dl class="info">
                <dt>Nama</dt><dd><a href="{{ route('customers.show', $order->customer) }}">{{ $order->customer->name }}</a>
                    @if ($order->customer->is_member)<span class="badge badge-member">Member</span>@endif</dd>
                <dt>No. HP</dt><dd>{{ \App\Support\Phone::format($order->customer->phone) }}</dd>
                <dt>Alamat</dt><dd>{{ $order->customer->address ?: '-' }}</dd>
                @if ($order->customer->is_member)
                    <dt>Stempel</dt><dd>{{ $order->customer->stamps }} / {{ config('laundry.stamp_target') }}</dd>
                @endif
            </dl>
        </div>

        <div class="card">
            <h2>Info order</h2>
            <dl class="info">
                <dt>Setelah selesai</dt><dd>{{ \App\Models\Order::DELIVERY_METHODS[$order->delivery_method] ?? $order->delivery_method }}</dd>
                <dt>Estimasi selesai</dt><dd>{{ $order->due_at?->translatedFormat('d M Y H:i') ?? '-' }}</dd>
                @if ($order->completed_at)<dt>Selesai</dt><dd>{{ $order->completed_at->translatedFormat('d M Y H:i') }}</dd>@endif
                @if ($order->paid_at)<dt>Dibayar</dt><dd>{{ $order->paid_at->translatedFormat('d M Y H:i') }} ({{ \App\Models\Order::PAYMENT_METHODS[$order->payment_method] ?? $order->payment_method }})</dd>@endif
                <dt>Catatan</dt><dd>{{ $order->notes ?: '-' }}</dd>
                @if ($order->mitra)
                    <dt>Mitra</dt><dd>{{ $order->mitra->name }}</dd>
                    <dt>Komisi mitra</dt><dd>{{ rupiah($order->commission_amount) }}
                        @if ($order->payout) <span class="badge badge-lunas">Sudah dicairkan</span>
                        @elseif ($order->status === 'selesai' && $order->isPaid()) <span class="badge badge-siap">Siap dicairkan</span>
                        @else <span class="badge">Menunggu order selesai &amp; lunas</span>@endif
                    </dd>
                @endif
            </dl>
        </div>
    </div>
</div>
@endsection

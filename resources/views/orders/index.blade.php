@extends('layouts.app')
@section('title', 'Daftar Order')

@section('content')
<div class="page-head">
    <h1>Daftar Order</h1>
    <a href="{{ route('orders.create') }}" class="btn">+ Order Baru</a>
</div>

<div class="card">
    <form method="GET" class="filters">
        <div>
            <label for="q">Cari</label>
            <input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="No. order, nama, atau HP">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Semua</option>
                <option value="proses" @selected(request('status') === 'proses')>Masih diproses</option>
                @foreach (\App\Models\Order::STATUSES as $key => $label)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="type">Jenis</label>
            <select id="type" name="type">
                <option value="">Semua</option>
                @foreach (\App\Models\Order::SERVICE_TYPES as $key => $label)
                    <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="serah">Serah terima</label>
            <select id="serah" name="serah">
                <option value="">Semua</option>
                <option value="outlet" @selected(request('serah') === 'outlet')>Diambil di outlet</option>
                <option value="antar" @selected(request('serah') === 'antar')>Diantar kurir</option>
                <option value="dropbox" @selected(request('serah') === 'dropbox')>Kembali ke dropbox</option>
            </select>
        </div>
        <div>
            <label for="payment">Pembayaran</label>
            <select id="payment" name="payment">
                <option value="">Semua</option>
                <option value="belum" @selected(request('payment') === 'belum')>Belum bayar</option>
                <option value="lunas" @selected(request('payment') === 'lunas')>Lunas</option>
            </select>
        </div>
        <div>
            <label for="mitra">Mitra</label>
            <select id="mitra" name="mitra">
                <option value="">Semua</option>
                @foreach ($mitras as $m)
                    <option value="{{ $m->id }}" @selected((string) request('mitra') === (string) $m->id)>{{ $m->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-outline">Terapkan</button>
    </form>

    <div class="table-wrap">
        <table class="rtable">
            <thead>
            <tr>
                <th>No. Order</th>
                <th>Pelanggan</th>
                <th>Jenis</th>
                <th>Status</th>
                <th>Bayar</th>
                <th class="num">Berat</th>
                <th class="num">Total</th>
                <th>Masuk</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td data-label="No. Order"><a href="{{ route('orders.show', $order) }}" class="strong">{{ $order->code }}</a>
                        </td>
                    <td data-label="Pelanggan">{{ $order->customer->name }}<br><span class="small muted">{{ \App\Support\Phone::format($order->customer->phone) }}</span></td>
                    <td data-label="Jenis"><span class="badge type-{{ $order->serviceType() }}">{{ $order->serviceTypeLabel() }}</span>@if ($order->mitra)<br><span class="small muted">{{ $order->mitra->name }}</span>@endif</td>
                    <td data-label="Status"><span class="badge badge-{{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                    <td data-label="Bayar"><span class="badge badge-{{ $order->payment_status }}">{{ $order->isPaid() ? 'Lunas' : 'Belum' }}</span></td>
                    <td data-label="Berat" class="num">{{ $order->total_kg > 0 ? qty($order->total_kg).' kg' : '-' }}</td>
                    <td data-label="Total" class="num">{{ rupiah($order->total) }}</td>
                    <td data-label="Masuk" class="small nowrap">{{ $order->created_at->translatedFormat('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">Belum ada order.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $orders->links() }}
</div>
@endsection

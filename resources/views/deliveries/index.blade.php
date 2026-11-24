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
                <tr>
                    <td data-label="Jam" class="nowrap strong">{{ $delivery->scheduled_at->format('H:i') }}</td>
                    <td data-label="Jenis"><span class="badge">{{ $delivery->typeLabel() }}</span></td>
                    <td data-label="Tujuan">
                        {{ $delivery->targetName() }} @if ($delivery->mitra)<span class="small muted">(mitra)</span>@endif<br>
                        <span class="small">{{ $delivery->address }}</span>
                        @if ($delivery->targetPhone())
                            <br><a href="{{ \App\Support\Phone::waLink($delivery->targetPhone()) }}" target="_blank" rel="noopener" class="small">{{ \App\Support\Phone::format($delivery->targetPhone()) }}</a>
                        @endif
                        @if ($delivery->notes)<br><span class="small muted">{{ $delivery->notes }}</span>@endif
                    </td>
                    <td data-label="Order">@if ($delivery->order)<a href="{{ route('orders.show', $delivery->order) }}">{{ $delivery->order->code }}</a>@else - @endif</td>
                    <td data-label="Status"><span class="badge badge-{{ $delivery->status }}">{{ $delivery->statusLabel() }}</span>
                        @if ($delivery->courier)<br><span class="small muted">{{ $delivery->courier->name }}</span>@endif</td>
                    <td data-label="Ubah">
                        <form method="POST" action="{{ route('deliveries.status', $delivery) }}" class="inline-form">
                            @csrf @method('PATCH')
                            <select name="status" aria-label="Status">
                                @foreach (\App\Models\Delivery::STATUSES as $key => $label)
                                    <option value="{{ $key }}" @selected($delivery->status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <select name="courier_id" aria-label="Petugas">
                                <option value="">Petugas</option>
                                @foreach ($couriers as $c)
                                    <option value="{{ $c->id }}" @selected($delivery->courier_id === $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm">Simpan</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Tidak ada jadwal di tanggal ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

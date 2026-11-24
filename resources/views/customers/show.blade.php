@extends('layouts.app')
@section('title', $customer->name)

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $customer->name }} @if ($customer->is_member)<span class="badge badge-member">{{ $customer->member_code }}</span>@endif</h1>
        <div class="sub">{{ \App\Support\Phone::format($customer->phone) }}</div>
    </div>
    <div class="row">
        <a href="{{ route('orders.create', ['customer' => $customer->id]) }}" class="btn">+ Order</a>
        <a href="{{ $customer->waLink() }}" target="_blank" rel="noopener" class="btn btn-green">WhatsApp</a>
        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline">Ubah</a>
    </div>
</div>

<div class="grid grid-2">
    <div class="stack">
        <div class="card">
            <h2>Data</h2>
            <dl class="info">
                <dt>Alamat</dt><dd>{{ $customer->address ?: '-' }}</dd>
                <dt>Member sejak</dt><dd>{{ $customer->member_since?->translatedFormat('d F Y') ?? '-' }}</dd>
                <dt>Catatan</dt><dd>{{ $customer->notes ?: '-' }}</dd>
            </dl>
        </div>

        <div class="card">
            <h2>Antar-jemput</h2>
            <dl class="info">
                <dt>Jarak</dt>
                <dd>
                    @if ($distance !== null)
                        {{ qty($distance) }} km {{ $customer->distance_source === 'jalan' ? 'lewat jalan' : '(garis lurus)' }}
                    @else
                        <span class="muted">Belum ada titik lokasi</span>
                    @endif
                </dd>
                <dt>Zona</dt>
                <dd>
                    @if ($zone)
                        {{ $zone->name }} · {{ rupiah($zone->fee) }}/perjalanan
                    @elseif ($distance !== null)
                        <span class="badge badge-siap">Di luar zona</span>
                    @else
                        -
                    @endif
                </dd>
                <dt>Promo ongkir</dt><dd>{{ $customer->deliveryPromoLabel() ?? '-' }}</dd>
            </dl>
            <div class="row" style="margin-top: 10px">
                @if ($customer->directionsUrl())
                    <a href="{{ $customer->directionsUrl() }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Buka di Google Maps</a>
                @endif
                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline btn-sm">{{ $customer->hasLocation() ? 'Ubah lokasi' : 'Atur lokasi' }}</a>
            </div>
        </div>

        @if ($customer->is_member)
            @php($target = config('laundry.stamp_target'))
            <div class="card">
                <h2>Kartu stempel</h2>
                <div class="stamps" style="margin-bottom: 12px" role="img" aria-label="{{ min($customer->stamps, $target) }} dari {{ $target }} stempel">
                    @for ($i = 1; $i <= $target; $i++)
                        <div @class(['stamp', 'filled' => $i <= $customer->stamps])>{{ $i }}</div>
                    @endfor
                </div>
                <p>Saldo: <span class="strong">{{ $customer->stamps }} stempel</span>. Hadiah: {{ config('laundry.stamp_reward') }}.</p>
                @if ($customer->canRedeem())
                    <form method="POST" action="{{ route('customers.redeem', $customer) }}" onsubmit="return confirm('Tukar {{ $target }} stempel dengan hadiah?')">
                        @csrf
                        <button type="submit" class="btn btn-green">Tukar Hadiah</button>
                    </form>
                @else
                    <p class="small muted">Kurang {{ $target - $customer->stamps }} stempel lagi.</p>
                @endif
            </div>
        @endif
    </div>

    <div class="stack">
        <div class="card">
            <h2>Order terakhir</h2>
            <div class="table-wrap">
                <table>
                    <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td><a href="{{ route('orders.show', $order) }}">{{ $order->code }}</a><br><span class="small muted">{{ $order->created_at->translatedFormat('d M Y') }}</span></td>
                            <td><span class="badge badge-{{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                            <td class="num">{{ rupiah($order->total) }}</td>
                        </tr>
                    @empty
                        <tr><td class="empty">Belum ada order.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($stampLogs->isNotEmpty())
            <div class="card">
                <h2>Riwayat stempel</h2>
                <div class="table-wrap">
                    <table>
                        <tbody>
                        @foreach ($stampLogs as $log)
                            <tr>
                                <td class="small nowrap">{{ $log->created_at->translatedFormat('d M Y') }}</td>
                                <td>{{ $log->note }}</td>
                                <td class="num">{{ $log->change > 0 ? '+'.$log->change : $log->change }}</td>
                                <td class="num muted">{{ $log->balance_after }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

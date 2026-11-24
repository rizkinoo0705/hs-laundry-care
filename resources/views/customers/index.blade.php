@extends('layouts.app')
@section('title', 'Pelanggan & Member')

@section('content')
<div class="page-head">
    <h1>Pelanggan &amp; Member</h1>
    <a href="{{ route('customers.create') }}" class="btn">+ Pelanggan</a>
</div>

<div class="card">
    <form method="GET" class="filters">
        <div>
            <label for="q">Cari</label>
            <input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Nama, HP, atau kode member">
        </div>
        <div>
            <label class="check" style="margin-top: 20px"><input type="checkbox" name="member" value="1" @checked(request('member') === '1')> Member saja</label>
        </div>
        <button type="submit" class="btn btn-outline">Cari</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Nama</th><th>No. HP</th><th>Member</th><th class="num">Stempel</th><th class="num">Order</th></tr>
            </thead>
            <tbody>
            @forelse ($customers as $customer)
                <tr>
                    <td><a href="{{ route('customers.show', $customer) }}" class="strong">{{ $customer->name }}</a></td>
                    <td>{{ \App\Support\Phone::format($customer->phone) }}</td>
                    <td>@if ($customer->is_member)<span class="badge badge-member">{{ $customer->member_code }}</span>@else<span class="muted">-</span>@endif</td>
                    <td class="num">
                        @if ($customer->is_member)
                            {{ $customer->stamps }}/{{ config('laundry.stamp_target') }}
                            @if ($customer->canRedeem())<br><span class="badge badge-siap">Bisa tukar hadiah</span>@endif
                        @else - @endif
                    </td>
                    <td class="num">{{ $customer->orders_count }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Belum ada pelanggan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $customers->links() }}
</div>
@endsection

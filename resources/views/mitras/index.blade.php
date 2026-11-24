@extends('layouts.app')
@section('title', 'Mitra Dropbox')

@section('content')
<div class="page-head">
    <div>
        <h1>Mitra Dropbox</h1>
        <div class="sub">Outlet mitra yang menerima titipan cucian dan mendapat komisi.</div>
    </div>
    <a href="{{ route('mitras.create') }}" class="btn">+ Mitra</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="rtable">
            <thead>
            <tr><th>Mitra</th><th>Komisi</th><th class="num">Order bulan ini</th><th class="num">Kg bulan ini</th><th class="num">Komisi belum dibayar</th><th>Status</th></tr>
            </thead>
            <tbody>
            @forelse ($mitras as $mitra)
                <tr>
                    <td data-label="Mitra"><a href="{{ route('mitras.show', $mitra) }}" class="strong">{{ $mitra->name }}</a><br><span class="small muted">{{ $mitra->owner_name }} · {{ \App\Support\Phone::format($mitra->phone) }}</span></td>
                    <td data-label="Komisi">{{ $mitra->commissionLabel() }}</td>
                    <td data-label="Order bulan ini" class="num">{{ $mitra->orders_month }}</td>
                    <td data-label="Kg bulan ini" class="num">{{ qty($mitra->kg_month ?? 0) }}</td>
                    <td data-label="Komisi belum dibayar" class="num strong">{{ rupiah($mitra->unpaid_commission ?? 0) }}</td>
                    <td data-label="Status">@if ($mitra->active)<span class="badge badge-selesai">Aktif</span>@else<span class="badge">Nonaktif</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Belum ada mitra.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

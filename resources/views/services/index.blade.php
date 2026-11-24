@extends('layouts.app')
@section('title', 'Layanan & Harga')

@section('content')
<div class="page-head">
    <h1>Layanan &amp; Harga</h1>
    <a href="{{ route('services.create') }}" class="btn">+ Layanan</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="rtable">
            <thead><tr><th>Nama</th><th>Kategori</th><th class="num">Harga</th><th class="num">Estimasi</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($services as $service)
                <tr>
                    <td data-label="Nama" class="strong">{{ $service->name }}</td>
                    <td data-label="Kategori">{{ $service->categoryLabel() }}</td>
                    <td data-label="Harga" class="num">{{ rupiah($service->price) }} / {{ $service->unit }}@if ($service->hasMinimum())<br><span class="small muted">min. {{ qty($service->min_qty) }} {{ $service->unit }}</span>@endif
                        @if ($service->staff_commission > 0)<br><span class="small muted">komisi petugas {{ rupiah($service->staff_commission) }}/{{ $service->unit }}</span>@endif</td>
                    <td data-label="Estimasi" class="num">{{ $service->duration_hours }} jam</td>
                    <td data-label="Status">@if ($service->active)<span class="badge badge-selesai">Aktif</span>@else<span class="badge">Nonaktif</span>@endif</td>
                    <td data-label="" class="num"><a href="{{ route('services.edit', $service) }}" class="btn btn-outline btn-sm">Ubah</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Belum ada layanan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Layanan & Harga')

@section('content')
<div class="page-head">
    <h1>Layanan &amp; Harga</h1>
    <a href="{{ route('services.create') }}" class="btn">+ Layanan</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nama</th><th>Kategori</th><th class="num">Harga</th><th class="num">Estimasi</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($services as $service)
                <tr>
                    <td class="strong">{{ $service->name }}</td>
                    <td>{{ $service->categoryLabel() }}</td>
                    <td class="num">{{ rupiah($service->price) }} / {{ $service->unit }}</td>
                    <td class="num">{{ $service->duration_hours }} jam</td>
                    <td>@if ($service->active)<span class="badge badge-selesai">Aktif</span>@else<span class="badge">Nonaktif</span>@endif</td>
                    <td class="num"><a href="{{ route('services.edit', $service) }}" class="btn btn-outline btn-sm">Ubah</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Belum ada layanan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

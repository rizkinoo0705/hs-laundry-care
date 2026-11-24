@extends('layouts.app')
@section('title', 'Pengguna')

@section('content')
<div class="page-head">
    <h1>Pengguna</h1>
    <a href="{{ route('users.create') }}" class="btn">+ Pengguna</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="rtable">
            <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $user)
                <tr>
                    <td data-label="Nama" class="strong">{{ $user->name }}</td>
                    <td data-label="Email">{{ $user->email }}</td>
                    <td data-label="Peran">{{ $user->roleLabel() }}@if ($user->mitra)<br><span class="small muted">{{ $user->mitra->name }}</span>@endif</td>
                    <td data-label="Status">@if ($user->active)<span class="badge badge-selesai">Aktif</span>@else<span class="badge">Nonaktif</span>@endif</td>
                    <td data-label="" class="num"><a href="{{ route('users.edit', $user) }}" class="btn btn-outline btn-sm">Ubah</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

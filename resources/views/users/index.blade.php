@extends('layouts.app')
@section('title', 'Pengguna')

@section('content')
<div class="page-head">
    <h1>Pengguna</h1>
    <a href="{{ route('users.create') }}" class="btn">+ Pengguna</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $user)
                <tr>
                    <td class="strong">{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->roleLabel() }}@if ($user->mitra)<br><span class="small muted">{{ $user->mitra->name }}</span>@endif</td>
                    <td>@if ($user->active)<span class="badge badge-selesai">Aktif</span>@else<span class="badge">Nonaktif</span>@endif</td>
                    <td class="num"><a href="{{ route('users.edit', $user) }}" class="btn btn-outline btn-sm">Ubah</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

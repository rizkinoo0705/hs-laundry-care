<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · {{ config('laundry.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
@php($user = auth()->user())
<div class="app">
    <aside class="sidebar">
        <a href="{{ route('dashboard') }}" class="brand">
            <span class="brand-badge">HS</span>
            <span>
                <span class="brand-name">Laundry &amp; Care</span><br>
                <span class="brand-tag">{{ config('laundry.tagline') }}</span>
            </span>
        </a>

        <nav class="nav" aria-label="Menu utama">
            @if ($user->hasRole('admin', 'kasir'))
                <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Dashboard</a>
                <a href="{{ route('orders.create') }}" @class(['active' => request()->routeIs('orders.create')])>+ Order Baru</a>
                <a href="{{ route('orders.index') }}" @class(['active' => request()->routeIs('orders.index', 'orders.show')])>Daftar Order</a>
                <a href="{{ route('customers.index') }}" @class(['active' => request()->routeIs('customers.*')])>Pelanggan &amp; Member</a>
                <a href="{{ route('deliveries.index') }}" @class(['active' => request()->routeIs('deliveries.*')])>Antar-Jemput</a>
            @endif
            @if ($user->isAdmin())
                <span class="nav-label">Admin</span>
                <a href="{{ route('mitras.index') }}" @class(['active' => request()->routeIs('mitras.*')])>Mitra Dropbox</a>
                <a href="{{ route('reports.index') }}" @class(['active' => request()->routeIs('reports.*')])>Laporan</a>
                <a href="{{ route('services.index') }}" @class(['active' => request()->routeIs('services.*')])>Layanan &amp; Harga</a>
                <a href="{{ route('users.index') }}" @class(['active' => request()->routeIs('users.*')])>Pengguna</a>
            @endif
            @if ($user->role === 'mitra')
                <a href="{{ route('mitra.portal') }}" class="active">Portal Mitra</a>
            @endif
        </nav>

        <div class="sidebar-foot">
            <div>{{ $user->name }}<br><span class="small">{{ $user->roleLabel() }}</span></div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">Keluar</button>
            </form>
        </div>
    </aside>

    <main class="main">
        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>

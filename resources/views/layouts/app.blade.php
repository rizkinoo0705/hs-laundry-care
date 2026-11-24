<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · {{ config('laundry.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('head')
</head>
<body>
@php($user = auth()->user())
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-top">
            <a href="{{ route('dashboard') }}" class="brand">
                <span class="brand-badge">HS</span>
                <span>
                    <span class="brand-name">Laundry &amp; Care</span><br>
                    <span class="brand-tag">{{ config('laundry.tagline') }}</span>
                </span>
            </a>
            <button type="button" class="menu-btn" id="menu-btn" aria-expanded="false" aria-controls="sidebar-menu">
                <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                Menu
            </button>
        </div>

        <div class="sidebar-menu" id="sidebar-menu">
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
                <a href="{{ route('staff.index') }}" @class(['active' => request()->routeIs('staff.*')])>Petugas &amp; Komisi</a>
                <a href="{{ route('reports.index') }}" @class(['active' => request()->routeIs('reports.*')])>Laporan</a>
                <a href="{{ route('services.index') }}" @class(['active' => request()->routeIs('services.*')])>Layanan &amp; Harga</a>
                <a href="{{ route('settings.delivery') }}" @class(['active' => request()->routeIs('settings.delivery*')])>Zona &amp; Ongkir</a>
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
        </div>
    </aside>

    @if ($user->hasRole('admin', 'kasir') && ! \Illuminate\Support\Facades\View::hasSection('hide-bottom-nav'))
        <nav class="bottom-nav" aria-label="Menu cepat">
            <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>Beranda</a>
            <a href="{{ route('orders.index') }}" @class(['active' => request()->routeIs('orders.index', 'orders.show')])>
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6"/></svg>Order</a>
            <a href="{{ route('orders.create') }}" class="bottom-nav-main">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>Baru</a>
            <a href="{{ route('customers.index') }}" @class(['active' => request()->routeIs('customers.*')])>
                <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>Pelanggan</a>
            <a href="{{ route('deliveries.index') }}" @class(['active' => request()->routeIs('deliveries.*')])>
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7zM7 19a2 2 0 1 0 0-.01M17 19a2 2 0 1 0 0-.01"/></svg>Antar</a>
        </nav>
    @endif

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
<script>
(function () {
    const btn = document.getElementById('menu-btn');
    const sidebar = document.getElementById('sidebar');
    btn.addEventListener('click', function () {
        const open = sidebar.classList.toggle('menu-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
})();
</script>
@stack('scripts')
</body>
</html>

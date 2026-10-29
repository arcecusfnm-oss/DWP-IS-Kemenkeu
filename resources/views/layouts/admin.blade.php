<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dasbor') - DWP-IS Kemenkeu</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Gelasio:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
@php
    // TODO: ganti dengan data dari controller / auth()->user()
    $adminNama = $adminNama ?? 'Admin Utama';
    $adminUnit = $adminUnit ?? 'Sekretariat DWP Pusat';

    $menu = [
        ['label' => 'Dasbor',               'icon' => 'dashboard', 'href' => '#', 'route' => 'admin.dashboard'],
        ['label' => 'Struktur Organisasi',  'icon' => 'org',       'href' => '#', 'route' => 'admin.struktur.*'],
        ['label' => 'Kelola Akun',          'icon' => 'users',     'href' => '#', 'route' => 'admin.akun.*'],
        ['label' => 'Kelola Bidang',        'icon' => 'shapes',    'href' => '#', 'route' => 'admin.bidang.*'],
        ['label' => 'Kelola Program Kerja', 'icon' => 'clipboard', 'href' => '#', 'route' => 'admin.proker.*'],
        ['label' => 'Laporan Kegiatan',     'icon' => 'file',      'href' => '#', 'route' => 'admin.laporan.*'],
        ['label' => 'Surat Masuk/Keluar',   'icon' => 'mail',      'href' => '#', 'route' => 'admin.surat.*'],
    ];
@endphp
<body>

    {{-- HEADER --}}
    <header class="topbar">
        <div class="topbar-left">
            <button id="menuBtn" class="menu-btn" type="button" aria-label="Buka menu">
                <x-icon name="menu" :size="24" />
            </button>
            <img class="logo" src="{{ asset('images/logo-dwp.png') }}" alt="Logo DWP">
            <div>
                <div class="brand serif">DWP-IS Kemenkeu</div>
                <div class="brand-sub">Kementrian Keuangan Republik Indonesia</div>
            </div>
        </div>

        <div class="topbar-right">
            <button class="icon-btn" type="button" aria-label="Notifikasi">
                <x-icon name="bell" :size="24" />
            </button>
            <div class="profile">
                <span class="avatar"><x-icon name="user" :size="24" /></span>
                <div class="profile-text">
                    <strong>{{ $adminNama }}</strong>
                    <span>{{ $adminUnit }}</span>
                </div>
            </div>
        </div>
    </header>

    {{-- SIDEBAR --}}
    <aside id="sidebar" class="sidebar">
        <div class="side-brand">
            <span class="side-brand-icon"><x-icon name="landmark" :size="16" /></span>
            <div>
                <strong class="serif">DWP Kemenkeu</strong>
                <small>Sistem Informasi</small>
            </div>
        </div>

        <nav class="side-nav">
            @foreach ($menu as $item)
                @php $aktif = request()->routeIs($item['route']); @endphp
                <a href="{{ $item['href'] }}" class="side-link {{ $aktif ? 'active' : '' }}">
                    <x-icon :name="$item['icon']" :size="24" />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <form class="side-logout" method="POST" action="{{ Route::has('logout') ? route('logout') : '#' }}">
            @csrf
            <button type="submit" class="logout-btn">
                <x-icon name="logout" :size="20" />
                Keluar
            </button>
        </form>
    </aside>

    {{-- KONTEN --}}
    <main class="main">
        @yield('content')
    </main>

    <script src="{{ asset('js/admin.js') }}"></script>
</body>
</html>
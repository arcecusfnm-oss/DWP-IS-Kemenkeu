<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dasbor') - DWP-IS Kemenkeu</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Gelasio:wght@500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    {{-- admin.css sebagai dasar (komponen halaman), penasihat.css menimpa warna header dan sidebar --}}
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/penasihat.css') }}?v={{ filemtime(public_path('css/penasihat.css')) }}">
    @stack('styles')
</head>
@php
    $penasihatNama = auth()->user()->nama_lengkap;
    $penasihatPeran = \Illuminate\Support\Facades\DB::table('peran')
        ->where('id_peran', auth()->user()->id_peran)
        ->value('nama_peran');

    // Penasihat: hanya lihat. Menu yang belum punya halaman diarahkan ke '#'.
    $menu = [
        ['label' => 'Dasbor', 'icon' => 'dashboard', 'href' => 'penasihat.dashboard', 'route' => 'penasihat.dashboard'],
        ['label' => 'Struktur Organisasi', 'icon' => 'org', 'href' => null, 'route' => 'penasihat.struktur.*'],
        ['label' => 'Anggota Bidang', 'icon' => 'users', 'href' => null, 'route' => 'penasihat.anggota.*'],
        ['label' => 'Program Kerja', 'icon' => 'clipboard', 'href' => 'penasihat.program-kerja.index', 'route' => 'penasihat.program-kerja.*'],
    ];
@endphp

<body class="tema-penasihat">

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

            {{-- Halaman profil dikunci role:Admin, jadi tidak ada tautan ke sana --}}
            <div class="profile">
                <span class="avatar">
                    <x-icon name="user" :size="24" />
                </span>
                <div class="profile-text">
                    <strong>{{ $penasihatNama }}</strong>
                    <span>{{ $penasihatPeran }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout-top" aria-label="Keluar">
                    <x-icon name="logout" :size="20" />
                    <span>Keluar</span>
                </button>
            </form>
        </div>
    </header>

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
                @php
                    $aktif = request()->routeIs($item['route']);
                    $url = $item['href'] && Route::has($item['href']) ? route($item['href']) : '#';
                @endphp
                <a href="{{ $url }}" class="side-link {{ $aktif ? 'active' : '' }}">
                    <x-icon :name="$item['icon']" :size="26" />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </aside>

    <main class="main">
        @yield('content')
    </main>

    <script src="{{ asset('js/admin.js') }}"></script>
    @stack('scripts')
</body>

</html>
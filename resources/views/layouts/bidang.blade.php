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

    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/bidang.css') }}?v={{ filemtime(public_path('css/bidang.css')) }}">

    @stack('styles')
</head>

@php
    $pengguna = auth()->user();
    $namaPeran = $pengguna->peran?->nama_peran ?? 'Pengguna Bidang';
    $bolehKegiatan = \App\Support\PeranProgramKerja::bolehKelolaKegiatan($pengguna);

    $menu = [
        [
            'label' => 'Dasbor',
            'icon' => 'dashboard',
            'href' => '#',
            'route' => null,
            'disabled' => true,
        ],
        [
            'label' => 'Struktur Organisasi',
            'icon' => 'org',
            'href' => '#',
            'route' => null,
            'disabled' => true,
        ],
        [
            'label' => 'Program Kerja',
            'icon' => 'clipboard',
            'href' => 'program-kerja.bidang-saya',
            'route' => ['program-kerja.bidang-saya', 'program-kerja.create', 'program-kerja.edit'],
            'disabled' => false,
        ],
        [
            'label' => 'Program Bidang Lain',
            'icon' => 'list',
            'href' => 'program-kerja.bidang-lain',
            'route' => 'program-kerja.bidang-lain',
            'disabled' => false,
        ],
        [
            'label' => 'Kegiatan',
            'icon' => 'calendar',
            'href' => 'kegiatan.index',
            'route' => ['kegiatan.index', 'kegiatan.create', 'program-kerja.kegiatan.create'],
            'disabled' => ! $bolehKegiatan,
        ],
        [
            'label' => 'Laporan Kegiatan',
            'icon' => 'file',
            'href' => '#',
            'route' => null,
            'disabled' => true,
        ],
    ];
@endphp

<body>

    <header class="topbar bidang-topbar">
        <div class="topbar-left">
            <button id="menuBtn" class="menu-btn" type="button" aria-label="Buka menu">
                <x-icon name="menu" :size="24" />
            </button>

            <img class="logo" src="{{ asset('images/logo-dwp.png') }}" alt="Logo DWP">

            <div>
                <div class="brand serif">DWP-IS Kemenkeu</div>
                <div class="brand-sub">Kementerian Keuangan Republik Indonesia</div>
            </div>
        </div>

        <div class="topbar-right">
            <button class="icon-btn" type="button" aria-label="Notifikasi">
                <x-icon name="bell" :size="24" />
            </button>

            <div class="profile">
                <span class="avatar">
                    <x-icon name="user" :size="24" />
                </span>

                <div class="profile-text">
                    <strong>{{ $pengguna->nama_lengkap }}</strong>
                    <span>{{ $namaPeran }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout-top">
                    <x-icon name="logout" :size="20" />
                    <span>Keluar</span>
                </button>
            </form>
        </div>
    </header>

    <aside id="sidebar" class="sidebar bidang-sidebar">
        <div class="side-brand">
            <span class="side-brand-icon">
                <x-icon name="landmark" :size="20" />
            </span>

            <div>
                <strong class="serif">DWP Kemenkeu</strong>
                <small>Sistem Informasi</small>
            </div>
        </div>

        <nav class="side-nav">
            @foreach ($menu as $item)
                @php
                    $aktif = $item['route'] && request()->routeIs(...(array) $item['route']);
                    $url = $item['disabled'] ? '#' : route($item['href']);
                @endphp

                <a href="{{ $url }}"
                    class="side-link {{ $aktif ? 'active' : '' }} {{ $item['disabled'] ? 'menu-disabled' : '' }}"
                    @if ($item['disabled']) aria-disabled="true" tabindex="-1" @endif>

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
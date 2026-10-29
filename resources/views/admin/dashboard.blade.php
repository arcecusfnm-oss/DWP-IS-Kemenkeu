@extends('layouts.admin')

@section('title', 'Dasbor')

@section('content')
@php
    // ===== DATA SEMENTARA, nanti diganti dari controller =====
    $namaUser  = $namaUser  ?? 'Ny. Xxxxx';
    $masaBakti = $masaBakti ?? '2026/2027';

    $stats = $stats ?? [
        ['label' => 'Total Anggota',    'angka' => '1.423', 'satuan' => '',       'icon' => 'users',     'badge' => 'Aktif Terverifikasi', 'badgeIcon' => 'check',     'badgeClass' => 'badge-green'],
        ['label' => 'Program Kerja',    'angka' => '24',    'satuan' => 'Aktif',  'icon' => 'calendar',  'badge' => 'Diperiksa Berkala',   'badgeIcon' => 'trend',     'badgeClass' => 'badge-blue'],
        ['label' => 'Surat Masuk',      'angka' => '12',    'satuan' => 'Baru',   'icon' => 'mail',      'badge' => 'Diajukan',            'badgeIcon' => 'send',      'badgeClass' => 'badge-yellow'],
        ['label' => 'Laporan Kegiatan', 'angka' => '8',     'satuan' => 'Berkas', 'icon' => 'checklist', 'badge' => 'Perlu Verifikasi',    'badgeIcon' => 'checklist', 'badgeClass' => 'badge-red'],
    ];

    $tahunStruktur = $tahunStruktur ?? '2026';

    $struktur = $struktur ?? [
        ['jabatan' => 'Ketua', 'unit' => 'DWP Kemenkeu', 'nama' => 'Ny. Name Xxxxxx Yyyyy Zzzzz', 'nip' => 'XXXXX', 'masaBakti' => '2026-2027', 'href' => '#'],
    ];
@endphp

<div class="content">

    {{-- Sambutan --}}
    <section class="welcome">
        <h1 class="serif">Selamat Datang, {{ $namaUser }}</h1>
        <p>Anda masuk sebagai Admin</p>
        <p class="masa">Masa Bakti: {{ $masaBakti }}</p>
    </section>

    {{-- Kartu statistik --}}
    <section class="stats">
        @foreach ($stats as $s)
            <div class="stat">
                <div class="stat-label">{{ $s['label'] }}</div>
                <div class="stat-row">
                    <div class="stat-num serif">
                        <b>{{ $s['angka'] }}</b>
                        @if ($s['satuan'])
                            <span>{{ $s['satuan'] }}</span>
                        @endif
                    </div>
                    <span class="stat-icon"><x-icon :name="$s['icon']" :size="24" /></span>
                </div>
                <div class="stat-badge">
                    <span class="badge {{ $s['badgeClass'] }}">
                        <x-icon :name="$s['badgeIcon']" :size="14" />
                        {{ $s['badge'] }}
                    </span>
                </div>
            </div>
        @endforeach
    </section>

    {{-- Struktur organisasi --}}
    <section class="struktur">
        <h2 class="serif">Struktur Organisasi</h2>
        <p class="tahun serif">DWP Kemenkeu {{ $tahunStruktur }}</p>

        <div class="org-list">
            @forelse ($struktur as $p)
                <article class="org-card">
                    <div class="org-tag">
                        <b>{{ $p['jabatan'] }}</b>
                        <small>{{ $p['unit'] }}</small>
                    </div>

                    <div class="org-body">
                        <span class="org-photo"><x-icon name="user" :size="40" /></span>
                        <div>
                            <div class="org-name">{{ $p['nama'] }}</div>
                            <div class="org-nip">NIP: {{ $p['nip'] }}</div>
                        </div>
                    </div>

                    <div class="org-foot">
                        <span>Masa Bakti: {{ $p['masaBakti'] }}</span>
                        <a href="{{ $p['href'] }}"><x-icon name="search" :size="12" /> Lihat</a>
                    </div>
                </article>
            @empty
                <p class="kosong">Struktur organisasi belum diisi.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
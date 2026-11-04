@extends('layouts.admin')

@section('title', 'Dasbor')

@section('content')
@php
    $namaUser = $namaUser ?? 'Ny. Xxxxx';
    $masaBakti = $masaBakti ?? '2026/2027';

    $stats = [
        [
            'label' => 'Total Anggota',
            'angka' => number_format($totalAnggota ?? 0, 0, ',', '.'),
            'href' => route('admin.akun.index'),
            'satuan' => '',
            'icon' => 'users',
            'badge' => 'Aktif Terverifikasi',
            'badgeIcon' => 'check',
            'badgeClass' => 'badge-green'
        ],
        [
            'label' => 'Program Kerja',
            'angka' => '24',
            'satuan' => 'Aktif',
            'icon' => 'calendar',
            'badge' => 'Diperiksa Berkala',
            'badgeIcon' => 'trend',
            'badgeClass' => 'badge-blue'
        ],
        [
            'label' => 'Surat Masuk',
            'angka' => '12',
            'satuan' => 'Baru',
            'icon' => 'mail',
            'badge' => 'Diajukan',
            'badgeIcon' => 'send',
            'badgeClass' => 'badge-yellow'
        ],
        [
            'label' => 'Laporan Kegiatan',
            'angka' => '8',
            'satuan' => 'Berkas',
            'icon' => 'checklist',
            'badge' => 'Perlu Verifikasi',
            'badgeIcon' => 'checklist',
            'badgeClass' => 'badge-red'
        ],
    ];

    $tahunStruktur = $tahunStruktur ?? '2026';

    $struktur = $struktur ?? [
        [
            'jabatan' => 'Ketua',
            'unit' => 'DWP Kemenkeu',
            'nama' => 'Ny. Name Xxxxxx Yyyyy Zzzzz',
            'nip' => 'XXXXX',
            'masaBakti' => '2026-2027',
            'href' => '#'
        ],
    ];
@endphp

<div class="content admin-dashboard">

    <section class="welcome">
        <h1 class="serif">
            Selamat Datang, {{ $namaUser }}
        </h1>

        <p>Anda masuk sebagai {{ $peranUser ?? 'Admin' }}</p>

        <p class="masa">
            Masa Bakti: {{ $masaBakti }}
        </p>
    </section>

    <section class="stats">
        @foreach ($stats as $s)
            @php $tag = !empty($s['href']) ? 'a' : 'div'; @endphp
            <{{ $tag }} class="stat"
                @if (!empty($s['href'])) href="{{ $s['href'] }}" style="text-decoration:none; color:inherit; display:block;" @endif>

                <div class="stat-label">
                    {{ $s['label'] }}
                </div>

                <div class="stat-row">
                    <div class="stat-num serif">
                        <b>{{ $s['angka'] }}</b>

                        @if (!empty($s['satuan']))
                            <span>{{ $s['satuan'] }}</span>
                        @endif
                    </div>

                    <span class="stat-icon">
                        <x-icon
                            :name="$s['icon']"
                            :size="28"
                        />
                    </span>
                </div>

                <div class="stat-badge">
                    <span class="badge {{ $s['badgeClass'] }}">
                        <span class="ikon">
                            <x-icon
                                :name="$s['badgeIcon']"
                                :size="16"
                            />
                        </span>

                        <span>{{ $s['badge'] }}</span>
                    </span>
                </div>

            </{{ $tag }}>
        @endforeach
    </section>

    <section class="struktur">

        <h2 class="serif">
            Struktur Organisasi
        </h2>

        <p class="tahun serif">
            DWP Kemenkeu {{ $tahunStruktur }}
        </p>

        <div class="struktur-scroll">
            <div class="org-list">
                @forelse ($struktur as $p)

                    <article class="org-card">

                        <div class="org-tag">
                            <b>{{ $p['jabatan'] }}</b>
                            <small>{{ $p['unit'] }}</small>
                        </div>

                        <div class="org-body">

                            <span class="org-photo">
                                <x-icon name="user" :size="40" />
                            </span>

                            <div>
                                <div class="org-name">
                                    {{ $p['nama'] }}
                                </div>

                                <div class="org-nip">
                                    NIP: {{ $p['nip'] }}
                                </div>
                            </div>

                        </div>

                        <div class="org-foot">
                            <span>
                                Masa Bakti: {{ $p['masaBakti'] }}
                            </span>

                            <a href="{{ $p['href'] }}">
                                <span class="ikon">
                                    <x-icon name="search" :size="12" />
                                </span>
                                <span>Lihat</span>
                            </a>
                        </div>

                    </article>

                @empty
                    <p class="kosong">
                        Struktur organisasi belum diisi.
                    </p>
                @endforelse
            </div>
        </div>

    </section>

</div>
@endsection
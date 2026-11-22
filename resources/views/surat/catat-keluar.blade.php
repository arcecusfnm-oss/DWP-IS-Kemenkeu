@extends('layouts.admin')

@section('title', 'Catat Surat Keluar')

@php
    $surat = (object) [];

    $ikon = [
        'hash'     => '<path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/>',
        'gedung'   => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 21v-4h6v4"/>',
        'kalender' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'chevron'  => '<path d="m6 9 6 6 6-6"/>',
    ];

    $nilai = fn ($k, $tipe = 'text') => old(
        $k,
        $tipe === 'date' ? substr((string) ($surat->{$k} ?? ''), 0, 10) : ($surat->{$k} ?? '')
    );

    $medan = [
        ['nama' => 'nomor_surat', 'label' => 'Nomor Surat', 'wajib' => true, 'ikon' => 'hash', 'maks' => 100,
         'hint' => 'Masukkan nomor sesuai naskah surat keluar'],
        ['nama' => 'tanggal_surat', 'label' => 'Tanggal Surat', 'wajib' => true, 'tipe' => 'date', 'ikon' => 'kalender',
         'hint' => 'Tanggal yang tercantum pada surat'],
        ['nama' => 'tujuan', 'label' => 'Tujuan Surat', 'wajib' => true, 'ikon' => 'gedung', 'penuh' => true, 'maks' => 255,
         'hint' => 'Instansi, unit kerja, atau penerima surat'],
        ['nama' => 'perihal', 'label' => 'Perihal', 'wajib' => true, 'tipe' => 'textarea', 'penuh' => true, 'maks' => 255, 'hitung' => true,
         'hint' => 'Ringkasan pokok isi surat'],
        ['nama' => 'id_bidang_terkait', 'label' => 'Bidang Terkait', 'wajib' => true, 'tipe' => 'select', 'penuh' => true,
         'opsi' => ['' => 'Pilih bidang'] + $bidang->mapWithKeys(fn ($b) => [$b->id_bidang => $b->nama_bidang])->all(),
         'hint' => 'Pilih bidang yang terkait dengan surat ini'],
        ['nama' => 'status_terkirim', 'label' => 'Status Terkirim', 'wajib' => true, 'tipe' => 'select',
         'opsi' => ['' => 'Pilih status', 'sudah' => 'Sudah terkirim', 'belum' => 'Belum terkirim', 'batal' => 'Dibatalkan'],
         'hint' => 'Pilih status pengiriman surat'],
        ['nama' => 'keterangan', 'label' => 'Keterangan', 'wajib' => false, 'tipe' => 'textarea', 'penuh' => true, 'maks' => 2000, 'baris' => 4,
         'hint' => 'Keterangan tambahan (opsional)'],
    ];
@endphp

@push('styles')
<style>
    .ubs { --ink:#3a2a22; --muted:#877368; --line:#f1d5c7; --fill:#fff1ea; --brand:#8d3812; --aksen:#c2653d; color:var(--ink); }
    .ubs *, .ubs *::before, .ubs *::after { box-sizing:border-box; }
    .ubs-crumb { display:flex; align-items:center; gap:6px; margin:0 0 8px; font-size:13px; color:var(--muted); }
    .ubs-crumb a { color:var(--muted); text-decoration:none; }
    .ubs-crumb a:hover { color:var(--brand); }
    .ubs-head, .ubs-card { background:#fff; border:1px solid var(--line); border-radius:14px; }
    .ubs-head { overflow:hidden; margin-bottom:16px; }
    .ubs-garis { height:4px; background:linear-gradient(90deg,#a5471f 0 33.33%,#f58b5e 33.33% 66.66%,#2e5b2a 66.66% 100%); }
    .ubs-head-isi { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px; padding:16px 20px; }
    .ubs-judul { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin:0; font-family:"Source Serif 4",Georgia,serif; font-size:26px; line-height:1.2; }
    .ubs-badge { padding:2px 10px; font-size:12px; font-weight:600; color:#20491a; background:#e8f3e5; border:1px solid #b9d9b2; border-radius:999px; }
    .ubs-sub { margin:6px 0 0; font-size:14px; color:var(--muted); }
    .ubs-grid { display:grid; grid-template-columns:minmax(0,1fr) 300px; gap:16px; align-items:start; }
    .ubs-card { padding:20px; }
    .ubs-aside { display:grid; gap:16px; }
    .ubs-aside h3 { margin:0 0 12px; font-size:15px; }
    .ubs-alert { margin:0 0 16px; padding:12px 16px; color:#8d3812; background:#fdece3; border:1px solid #f1b9a1; border-radius:10px; }
    .ubs-alert strong, .ubs-alert span { display:block; }
    .ubs-alert span { font-size:13px; }
    .ubs-sec { display:flex; align-items:center; gap:10px; margin:0 0 14px; }
    .ubs-sec + .ubs-fields { margin-bottom:22px; }
    .ubs-no { width:24px; height:24px; display:grid; place-items:center; flex:none; font-size:12px; font-weight:700; color:var(--brand); background:#ffdbc8; border-radius:50%; }
    .ubs-sec h2 { margin:0; font-size:16px; }
    .ubs-sec p { margin:0; font-size:12px; color:var(--muted); }
    .ubs-fields { display:grid; grid-template-columns:1fr 1fr; gap:14px 16px; }
    .ubs-full { grid-column:1 / -1; }
    .ubs-labelrow { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:6px; }
    .ubs-labelrow label { font-size:13px; font-weight:600; }
    .ubs-labelrow em { font-style:normal; color:var(--brand); }
    .ubs-hitung { font-size:11.5px; color:var(--muted); }
    .ubs-input { position:relative; }
    .ubs-input input, .ubs-input select, .ubs-textarea { width:100%; font:inherit; color:var(--ink); background:var(--fill); border:1px solid var(--line); border-radius:10px; }
    .ubs-input input, .ubs-input select { min-height:44px; padding:0 38px 0 12px; }
    .ubs-input input[type="date"] { padding-right:12px; }
    .ubs-input input[type="date"]::-webkit-calendar-picker-indicator { cursor:pointer; }
    .ubs-input select { appearance:none; }
    .ubs-input > svg { position:absolute; right:12px; top:50%; transform:translateY(-50%); color:var(--aksen); pointer-events:none; }
    .ubs-textarea { min-height:88px; padding:12px; resize:vertical; }
    .ubs-input input:focus-visible, .ubs-input select:focus-visible, .ubs-textarea:focus-visible, .ubs-btn-batal:focus-visible, .ubs-btn-simpan:focus-visible { outline:2px solid var(--aksen); outline-offset:2px; }
    .ubs [aria-invalid="true"] { border-color:var(--brand); background:#fdece3; }
    .ubs-hint, .ubs-galat { margin:6px 0 0; font-size:11.5px; color:var(--muted); }
    .ubs-galat { font-size:12.5px; font-weight:600; color:var(--brand); }
    .ubs-hint:empty { display:none; }
    .ubs-aksi { display:flex; flex-wrap:wrap; justify-content:space-between; gap:10px; margin-top:22px; }
    .ubs-btn-batal, .ubs-btn-simpan { min-height:44px; padding:0 22px; display:inline-flex; align-items:center; justify-content:center; gap:8px; font:inherit; font-weight:600; border-radius:10px; }
    .ubs-btn-batal { color:var(--brand); text-decoration:none; background:var(--fill); border:1px solid var(--line); }
    .ubs-btn-batal:hover { background:#ffdbc8; }
    .ubs-btn-simpan { padding:0 28px; color:#fff; background:var(--brand); border:0; cursor:pointer; }
    .ubs-btn-simpan:hover { background:#7a2f0e; }
    .ubs-info { padding:16px; font-size:12.5px; line-height:1.5; color:var(--ink); background:#fff6f1; border:1px solid var(--line); border-radius:14px; }
    .ubs-info strong { display:block; margin-bottom:6px; font-size:13px; }
    @media (max-width:1024px) { .ubs-grid { grid-template-columns:1fr; } }
    @media (max-width:640px) {
        .ubs-fields { grid-template-columns:1fr; }
        .ubs-card { padding:16px; }
        .ubs-judul { font-size:22px; }
        .ubs-btn-batal, .ubs-btn-simpan { flex:1 1 100%; }
    }
</style>
@endpush

@section('content')
<div class="content ubs">
    <nav class="ubs-crumb" aria-label="Breadcrumb">
        <a href="{{ route('surat.index', ['jenis' => 'keluar']) }}">Persuratan</a>
        <span aria-hidden="true">/</span>
        <span>Catat Surat Keluar</span>
    </nav>

    <header class="ubs-head">
        <div class="ubs-garis" aria-hidden="true"></div>
        <div class="ubs-head-isi">
            <div>
                <h1 class="ubs-judul">Catat Surat Keluar <span class="ubs-badge">Registrasi</span></h1>
                <p class="ubs-sub">Registrasi surat keluar dan pencatatan tujuan surat.</p>
            </div>
        </div>
    </header>

    <div class="ubs-grid">
        <main class="ubs-card">
            @if ($errors->any())
                <div class="ubs-alert" role="alert">
                    <strong>Data belum dapat disimpan</strong>
                    <span>Periksa kembali isian yang ditandai dan lengkapi sesuai ketentuan.</span>
                </div>
            @endif

            @if (session('galat'))
                <div class="ubs-alert" role="alert">
                    <strong>Surat belum dapat dicatat</strong>
                    <span>{{ session('galat') }}</span>
                </div>
            @endif

            <form action="{{ route('surat.keluar.store') }}" method="POST">
                @csrf

                <div class="ubs-sec">
                    <span class="ubs-no">1</span>
                    <div>
                        <h2>Informasi Surat</h2>
                        <p>Lengkapi data surat yang akan dikirim.</p>
                    </div>
                </div>

                <div class="ubs-fields">
                    @foreach ($medan as $m)
                        @include('surat._isian', ['m' => $m])
                    @endforeach
                </div>

                <div class="ubs-aksi">
                    <a href="{{ route('surat.index', ['jenis' => 'keluar']) }}" class="ubs-btn-batal">Batal</a>
                    <button type="submit" class="ubs-btn-simpan">Simpan Surat Keluar</button>
                </div>
            </form>
        </main>

        <aside class="ubs-aside">
            <section class="ubs-card">
                <h3>Informasi Pencatatan</h3>
                <div class="ubs-info">
                    <strong>Nomor surat dicatat sesuai naskah</strong>
                    Nomor surat diisi berdasarkan dokumen resmi. Nomor yang sama tidak dapat didaftarkan dua kali pada surat keluar.
                </div>
            </section>

            <section class="ubs-card">
                <h3>Petunjuk Pencatatan</h3>
                <div class="ubs-info">
                    <strong>Pastikan data surat benar</strong>
                    <ul>
                        <li>Nomor surat wajib diisi dan tidak boleh duplikat.</li>
                        <li>Tanggal surat diisi sesuai dokumen.</li>
                        <li>Tujuan surat harus jelas.</li>
                        <li>Perihal merangkum isi surat.</li>
                    </ul>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection

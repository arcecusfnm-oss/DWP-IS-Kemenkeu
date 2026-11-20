@extends('layouts.admin')

@section('title', 'Catat Surat Masuk')

@php
    $surat = (object) [];

    $ikon = [
        'hash'     => '<path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/>',
        'gedung'   => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 21v-4h6v4"/>',
        'kalender' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'surel'    => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'klip'     => '<path d="m21 12-8.5 8.5a5 5 0 0 1-7-7L14 5a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 8"/>',
        'chevron'  => '<path d="m6 9 6 6 6-6"/>',
    ];

    $nilai = fn ($k, $tipe = 'text') => old(
        $k,
        $tipe === 'date' ? substr((string) ($surat->{$k} ?? ''), 0, 10) : ($surat->{$k} ?? '')
    );

    $medan = [
        ['nama' => 'nomor_surat', 'label' => 'Nomor Surat', 'wajib' => true, 'ikon' => 'hash', 'maks' => 100,
         'hint' => 'Sesuai nomor pada naskah asli surat'],
        ['nama' => 'asal_surat', 'label' => 'Asal Surat', 'wajib' => true, 'ikon' => 'gedung', 'kiri' => true, 'penuh' => true, 'maks' => 255,
         'hint' => 'Nama instansi atau unit pengirim surat'],
        ['nama' => 'tanggal_surat', 'label' => 'Tanggal Surat', 'wajib' => true, 'tipe' => 'date', 'ikon' => 'kalender',
         'hint' => 'Tanggal yang tercantum pada surat'],
        ['nama' => 'tanggal_diterima', 'label' => 'Tanggal Diterima', 'wajib' => true, 'tipe' => 'date', 'ikon' => 'surel',
         'hint' => 'Tanggal surat diterima oleh sekretariat'],
        ['nama' => 'lampiran', 'label' => 'Lampiran Digital', 'tipe' => 'file', 'ikon' => 'klip', 'penuh' => true,
         'hint' => 'Opsional. PDF, JPG, PNG, DOC, DOCX, XLS, XLSX. Maksimal 5 MB'],
        ['nama' => 'perihal', 'label' => 'Perihal', 'wajib' => true, 'tipe' => 'textarea', 'penuh' => true, 'maks' => 255, 'hitung' => true,
         'hint' => 'Ringkasan pokok isi surat'],
    ];

    $medanDisposisi = [
        ['nama' => 'disposisi', 'label' => 'Petunjuk / Arahan Disposisi', 'tipe' => 'textarea', 'penuh' => true, 'maks' => 2000, 'baris' => 3,
         'hint' => 'Arahan pimpinan atau tindak lanjut (opsional)'],
        ['nama' => 'tanggal_disposisi', 'label' => 'Tanggal Disposisi', 'tipe' => 'date', 'ikon' => 'kalender',
         'hint' => 'Tanggal disposisi diberikan (opsional)'],
        ['nama' => 'keterangan', 'label' => 'Keterangan', 'tipe' => 'textarea', 'penuh' => true, 'maks' => 2000, 'baris' => 2,
         'hint' => 'Catatan tambahan (opsional)'],
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
    .ubs-alert { display:flex; gap:12px; align-items:flex-start; margin:0 0 16px; padding:12px 16px; color:#8d3812; background:#fdece3; border:1px solid #f1b9a1; border-radius:10px; }
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
    .ubs-input select { appearance:none; }
    .ubs-input input[type="file"] { padding:0 38px 0 12px; font-size:14px; line-height:44px; cursor:pointer; }
    .ubs-input input[type="file"]::file-selector-button { display:none; }
    .ubs-input > svg { position:absolute; right:12px; top:50%; transform:translateY(-50%); color:var(--aksen); pointer-events:none; }
    .ubs-kiri input { padding:0 12px 0 38px; }
    .ubs-kiri > svg { right:auto; left:12px; }
    .ubs-input input[type="date"]::-webkit-calendar-picker-indicator { position:absolute; right:0; top:0; width:40px; height:100%; opacity:0; cursor:pointer; }
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
        <a href="{{ route('surat.index', ['jenis' => 'masuk']) }}">Persuratan</a>
        <span aria-hidden="true">/</span>
        <span>Catat Surat Masuk</span>
    </nav>

    <header class="ubs-head">
        <div class="ubs-garis" aria-hidden="true"></div>
        <div class="ubs-head-isi">
            <div>
                <h1 class="ubs-judul">Catat Surat Masuk <span class="ubs-badge">Registrasi</span></h1>
                <p class="ubs-sub">Registrasi surat masuk dan pencatatan arahan disposisi.</p>
            </div>
        </div>
    </header>

    <div class="ubs-grid">
        <main class="ubs-card">
            @if ($errors->any())
                <div class="ubs-alert" role="alert">
                    <div>
                        <strong>Data belum dapat disimpan</strong>
                        <span>Periksa kembali isian yang ditandai dan lengkapi sesuai ketentuan.</span>
                    </div>
                </div>
            @endif

            <form action="{{ route('surat.simpan-masuk') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="ubs-sec">
                    <span class="ubs-no">1</span>
                    <div>
                        <h2>Informasi Surat</h2>
                        <p>Lengkapi data sesuai surat yang diterima.</p>
                    </div>
                </div>

                <div class="ubs-fields">
                    @foreach ($medan as $m)
                        @include('surat._isian', ['m' => $m])
                    @endforeach
                </div>

                <div class="ubs-sec">
                    <span class="ubs-no">2</span>
                    <div>
                        <h2>Disposisi & Keterangan</h2>
                        <p>Bagian ini dapat dilengkapi jika sudah tersedia.</p>
                    </div>
                </div>

                <div class="ubs-fields">
                    @foreach ($medanDisposisi as $m)
                        @include('surat._isian', ['m' => $m])
                    @endforeach
                </div>

                <div class="ubs-aksi">
                    <a href="{{ route('surat.index', ['jenis' => 'masuk']) }}" class="ubs-btn-batal">Batal</a>
                    <button type="submit" class="ubs-btn-simpan">Simpan Surat Masuk</button>
                </div>
            </form>
        </main>

        <aside class="ubs-aside">
            <section class="ubs-card">
                <h3>Nomor Agenda</h3>
                <div class="ubs-info">
                    <strong>Nomor dibuat otomatis oleh sistem</strong>
                    Nomor agenda akan diberikan saat data surat berhasil disimpan, mengikuti urutan agenda surat masuk pada bulan dan tahun penerimaan.
                </div>
            </section>

            <section class="ubs-card">
                <h3>Petunjuk Pencatatan</h3>
                <div class="ubs-info">
                    <strong>Pastikan data sesuai naskah asli</strong>
                    <ul>
                        <li>Nomor surat dan asal surat wajib diisi.</li>
                        <li>Tanggal surat tidak boleh melewati tanggal diterima.</li>
                        <li>Nomor surat yang sama tidak dapat dicatat dua kali.</li>
                        <li>Lampiran bersifat opsional, maksimal 5 MB.</li>
                    </ul>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection

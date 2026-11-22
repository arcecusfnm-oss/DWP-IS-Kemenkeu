{{--
    resources/views/surat/edit.blade.php
    T-70 (B-038): form ubah data surat masuk dan surat keluar, mengikuti desain Ubah Surat Masuk.
    Data dari UbahSuratController::edit(); simpan ke PUT /surat/{jenis}/{id} (surat.update, T-69).

    Variabel: $jenis ('masuk'|'keluar'), $id, $surat (baris tabel), $daftarBidang (surat keluar), $pembuat (nama pencatat)
    Jenis surat hanya ditampilkan, tidak dikirim (AC 4); server menolak request yang membawa 'jenis'.
--}}
@extends('layouts.admin')

@section('title', 'Ubah Surat')

@php
    $masuk       = $jenis === 'masuk';
    $labelJenis  = $masuk ? 'Surat Masuk' : 'Surat Keluar';
    $jumlahGalat = $errors->count();

    $ikon = [
        'hash'     => '<path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/>',
        'gedung'   => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 21v-4h6v4"/>',
        'kalender' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'surel'    => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'kirim'    => '<path d="m22 2-7 20-4-9-9-4zM22 2 11 13"/>',
        'chevron'  => '<path d="m6 9 6 6 6-6"/>',
        'klip'     => '<path d="m21 12-8.5 8.5a5 5 0 0 1-7-7L14 5a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 8"/>',
    ];

    // Nilai awal: isian lama (setelah gagal validasi) atau data tersimpan. Tanggal dipotong ke Y-m-d.
    $nilai = fn ($k, $tipe = 'text') => old(
        $k,
        $tipe === 'date' ? substr((string) ($surat->{$k} ?? ''), 0, 10) : ($surat->{$k} ?? '')
    );
    $tgl = fn ($v, $f = 'd/m/Y') => $v ? \Carbon\Carbon::parse($v)->format($f) : '-';

    // Isian bagian 1 (nama kolom mengikuti migration dan aturan validasi T-69)
    if ($masuk) {
        $medan = [
            ['nama' => 'nomor_surat', 'label' => 'Nomor Surat', 'wajib' => true, 'ikon' => 'hash', 'maks' => 100,
             'hint' => 'Sesuai naskah asli dari instansi pengirim'],
            ['nama' => 'asal_surat', 'label' => 'Asal Surat', 'wajib' => true, 'ikon' => 'gedung', 'kiri' => true, 'penuh' => true, 'maks' => 255,
             'hint' => 'Nama unit kementerian, lembaga, atau instansi pengirim resmi'],
            ['nama' => 'tanggal_surat', 'label' => 'Tanggal Surat', 'wajib' => true, 'tipe' => 'date', 'ikon' => 'kalender',
             'hint' => 'Tanggal tertera pada kepala naskah dinas'],
            ['nama' => 'tanggal_diterima', 'label' => 'Tanggal Diterima', 'wajib' => true, 'tipe' => 'date', 'ikon' => 'surel',
             'hint' => 'Diterima oleh petugas loket sekretariat DWP'],
            ['nama' => 'lampiran', 'label' => 'Lampiran', 'wajib' => true, 'ikon' => 'klip', 'penuh' => true, 'maks' => 255,
            'hint' => 'Tuliskan keterangan lampiran, misalnya "1 berkas", atau "-" jika tidak ada'],
            ['nama' => 'perihal', 'label' => 'Perihal', 'wajib' => true, 'tipe' => 'textarea', 'penuh' => true, 'maks' => 255, 'hitung' => true,
            'hint' => 'Ringkasan pokok permasalahan atau urgensi isi surat'],
        ];
    } else {
        $medan = [
            ['nama' => 'nomor_surat', 'label' => 'Nomor Surat', 'wajib' => true, 'ikon' => 'hash', 'maks' => 100,
             'hint' => 'Nomor tercatat pada naskah surat keluar'],
            ['nama' => 'tanggal', 'label' => 'Tanggal Surat', 'wajib' => true, 'tipe' => 'date', 'ikon' => 'kalender',
             'hint' => 'Tanggal tertera pada kepala naskah dinas'],
            ['nama' => 'tujuan', 'label' => 'Tujuan', 'wajib' => true, 'ikon' => 'kirim', 'kiri' => true, 'penuh' => true, 'maks' => 255,
             'hint' => 'Nama instansi atau pihak tujuan surat'],
            ['nama' => 'status_terkirim', 'label' => 'Status Terkirim', 'wajib' => true, 'tipe' => 'select',
             'opsi' => ['sudah' => 'Sudah', 'belum' => 'Belum', 'batal' => 'Batal'], 'hint' => 'Posisi pengiriman surat keluar'],
            ['nama' => 'id_bidang_terkait', 'label' => 'Bidang Terkait', 'tipe' => 'select',
             'opsi' => $daftarBidang->pluck('nama_bidang', 'id_bidang')->all(),
             'hint' => 'Opsional: bidang yang berkaitan dengan surat'],
            ['nama' => 'perihal', 'label' => 'Perihal', 'wajib' => true, 'tipe' => 'textarea', 'penuh' => true, 'maks' => 255, 'hitung' => true,
             'hint' => 'Ringkasan pokok permasalahan atau urgensi isi surat'],
            ['nama' => 'keterangan', 'label' => 'Keterangan', 'tipe' => 'textarea', 'penuh' => true, 'maks' => 2000, 'baris' => 2,
             'hint' => 'Catatan tambahan (opsional)'],
        ];
    }

    // Isian bagian 2 (hanya surat masuk), urutan mengikuti template: Disposisi, Tanggal Dispo, Keterangan
    $medanDisposisi = [
        ['nama' => 'disposisi', 'label' => 'Petunjuk / Arahan Disposisi', 'tipe' => 'textarea', 'penuh' => true, 'maks' => 2000, 'baris' => 3, 'hint' => ''],
        ['nama' => 'tanggal_disposisi', 'label' => 'Tanggal Disposisi', 'tipe' => 'date', 'ikon' => 'kalender',
         'hint' => 'Waktu penyerahan lembar disposisi'],
        ['nama' => 'keterangan', 'label' => 'Keterangan', 'tipe' => 'textarea', 'penuh' => true, 'maks' => 2000, 'baris' => 2,
         'hint' => 'Catatan tambahan (opsional)'],
    ];

    // Jalur di kartu kanan: semuanya dari data tersimpan
    $namaPembuat = $pembuat ?? 'petugas';
    if ($masuk) {
        $adaDisposisi = filled($surat->disposisi) || filled($surat->tanggal_disposisi);
        $langkah = [
            ['Penerimaan & Registrasi', 'Dicatat oleh ' . $namaPembuat, $tgl($surat->tanggal_diterima), $adaDisposisi ? 'selesai' : 'aktif'],
            ['Lembar Disposisi', $adaDisposisi ? 'Arahan pimpinan tercatat' : 'Belum ada disposisi', $adaDisposisi ? $tgl($surat->tanggal_disposisi) : null, $adaDisposisi ? 'aktif' : 'tunggu'],
        ];
        $infoFormat = $surat->lampiran ? 'Berkas digital terlampir' : 'Belum ada berkas digital';
    } else {
        $labelStatus = ['sudah' => 'Sudah terkirim', 'belum' => 'Belum terkirim', 'batal' => 'Dibatalkan'][$surat->status_terkirim] ?? '-';
        $langkah = [
            ['Pencatatan Surat Keluar', 'Dicatat oleh ' . $namaPembuat, $tgl($surat->created_at), 'selesai'],
            ['Status Pengiriman', $labelStatus, null, $surat->status_terkirim === 'sudah' ? 'aktif' : 'tunggu'],
        ];
        $infoFormat = $labelStatus;
    }
    $pernahDisunting = (string) $surat->updated_at !== (string) $surat->created_at;
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
    .ubs-badge { padding:2px 10px; font-family:inherit; font-size:12px; font-weight:600; color:#20491a; background:#e8f3e5; border:1px solid #b9d9b2; border-radius:999px; }
    .ubs-sub { margin:6px 0 0; font-size:14px; color:var(--muted); }
    .ubs-format { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#fff6f1; border:1px solid var(--line); border-radius:10px; }
    .ubs-format-ikon { width:40px; height:40px; display:grid; place-items:center; color:var(--brand); background:#ffdbc8; border-radius:10px; }
    .ubs-format small { display:block; font-size:10.5px; letter-spacing:.06em; color:var(--muted); }
    .ubs-format strong { display:block; font-size:14px; }
    .ubs-format span { font-size:12px; color:var(--aksen); }

    .ubs-grid { display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:16px; align-items:start; }
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
    .ubs-sistem { display:inline-flex; align-items:center; gap:4px; font-size:11px; color:var(--muted); }
    .ubs-hitung { font-size:11.5px; color:var(--muted); }

    .ubs-input { position:relative; }
    .ubs-input input, .ubs-input select, .ubs-textarea {
        width:100%; font:inherit; color:var(--ink); background:var(--fill); border:1px solid var(--line); border-radius:10px;
    }
    .ubs-input input, .ubs-input select { min-height:44px; padding:0 38px 0 12px; }
    .ubs-input select { appearance:none; }
    .ubs-input input[type="file"] { padding:0 38px 0 12px; font-size:14px; line-height:44px; cursor:pointer; }
    .ubs-input input[type="file"]::file-selector-button { display:none; }
    .ubs-input > svg { position:absolute; right:12px; top:50%; transform:translateY(-50%); color:var(--aksen); pointer-events:none; }
    .ubs-kiri input { padding:0 12px 0 38px; }
    .ubs-kiri > svg { right:auto; left:12px; }
    .ubs-input input[type="date"]::-webkit-calendar-picker-indicator { position:absolute; right:0; top:0; width:40px; height:100%; opacity:0; cursor:pointer; }
    .ubs-textarea { min-height:88px; padding:12px; resize:vertical; }
    .ubs-input input:focus-visible, .ubs-input select:focus-visible, .ubs-textarea:focus-visible, .ubs-chip:focus-visible,
    .ubs-btn-batal:focus-visible, .ubs-btn-simpan:focus-visible { outline:2px solid var(--aksen); outline-offset:2px; }
    .ubs-input input:disabled { color:var(--muted); background:#f7ece6; cursor:not-allowed; }
    .ubs [aria-invalid="true"] { border-color:var(--brand); background:#fdece3; }
    .ubs-hint, .ubs-galat { margin:6px 0 0; font-size:11.5px; color:var(--muted); }
    .ubs-galat { font-size:12.5px; font-weight:600; color:var(--brand); }
    .ubs-hint:empty { display:none; }

    .ubs-chips { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-top:10px; font-size:11.5px; color:var(--muted); }
    .ubs-chip { padding:3px 10px; font:inherit; font-size:11.5px; color:var(--brand); background:var(--fill); border:1px solid var(--line); border-radius:999px; cursor:pointer; }
    .ubs-chip:hover { background:#ffdbc8; }

    .ubs-aksi { display:flex; flex-wrap:wrap; justify-content:space-between; gap:10px; margin-top:22px; }
    .ubs-btn-batal, .ubs-btn-simpan { min-height:44px; padding:0 22px; display:inline-flex; align-items:center; justify-content:center; gap:8px; font:inherit; font-weight:600; border-radius:10px; }
    .ubs-btn-batal { color:var(--brand); text-decoration:none; background:var(--fill); border:1px solid var(--line); }
    .ubs-btn-batal:hover { background:#ffdbc8; }
    .ubs-btn-simpan { padding:0 28px; color:#fff; background:var(--brand); border:0; cursor:pointer; }
    .ubs-btn-simpan:hover { background:#7a2f0e; }
    .ubs-jejak { display:flex; align-items:center; gap:8px; margin-top:14px; padding:10px 12px; font-size:12.5px; color:var(--muted); background:#fff6f1; border-radius:8px; }

    .ubs-tl { margin:0; padding:0; list-style:none; }
    .ubs-tl li { position:relative; padding:0 0 18px 34px; }
    .ubs-tl li:last-child { padding-bottom:0; }
    .ubs-tl li::before { content:""; position:absolute; left:10px; top:24px; bottom:2px; width:2px; background:var(--line); }
    .ubs-tl li:last-child::before { display:none; }
    .ubs-titik { position:absolute; left:0; top:0; width:22px; height:22px; display:grid; place-items:center; color:#fff; background:#2e5b2a; border-radius:50%; }
    .ubs-tl .aktif .ubs-titik { background:var(--aksen); }
    .ubs-tl .tunggu .ubs-titik { background:#fff; border:2px solid var(--line); }
    .ubs-tl strong { display:block; font-size:13.5px; }
    .ubs-tl .aktif strong { color:var(--brand); }
    .ubs-tl span, .ubs-tl small { display:block; font-size:12px; color:var(--muted); }
    .ubs-tl .aktif small { color:var(--aksen); }
    .ubs-kosong { margin:0; font-size:13px; color:var(--muted); }
    .ubs-kosong a { color:var(--brand); }
    .ubs-info { padding:16px; font-size:12.5px; line-height:1.5; color:var(--ink); background:#fff6f1; border:1px solid var(--line); border-radius:14px; }
    .ubs-info strong { display:flex; align-items:center; gap:6px; margin-bottom:6px; font-size:13px; }

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

    <header class="ubs-head">
        <div class="ubs-garis" aria-hidden="true"></div>
        <div class="ubs-head-isi">
            <div>
                <nav class="ubs-crumb" aria-label="Jejak halaman">
                    <a href="{{ route('surat.index', ['jenis' => $jenis]) }}">Persuratan</a>
                    <span aria-hidden="true">›</span>
                    <span aria-current="page">Ubah Surat</span>
                </nav>
                <h1 class="ubs-judul">Ubah {{ $labelJenis }} <span class="ubs-badge">Terdaftar Resmi</span></h1>
                <p class="ubs-sub">
                    Perbarui rincian informasi naskah {{ strtolower($labelJenis) }}
                    @if ($masuk) No. Agenda <b>{{ $surat->nomor_agenda }}</b>.@else Nomor <b>{{ $surat->nomor_surat }}</b>.@endif
                </p>
            </div>
            <div class="ubs-format">
                <span class="ubs-format-ikon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $ikon['surel'] !!}</svg>
                </span>
                <div>
                    <small>FORMAT DOKUMEN</small>
                    <strong>{{ $labelJenis }}</strong>
                    <span>{{ $infoFormat }}</span>
                </div>
            </div>
        </div>
    </header>

    <div class="ubs-grid">

        {{-- Kolom utama: form --}}
        <section class="ubs-card">
            @if (session('galat'))
                <div class="ubs-alert" role="alert"><div><strong>{{ session('galat') }}</strong></div></div>
            @elseif ($jumlahGalat)
                <div class="ubs-alert" role="alert">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                    <div>
                        <strong>Terdapat {{ $jumlahGalat }} kesalahan pengisian formulir</strong>
                        <span>Silakan periksa dan perbaiki isian di bawah ini sebelum menyimpan perubahan.</span>
                    </div>
                </div>
            @endif

            <form action="{{ route('surat.update', [$jenis, $id]) }}" method="POST" novalidate>
                @csrf
                @method('PUT')

                <div class="ubs-sec">
                    <span class="ubs-no">1</span>
                    <div>
                        <h2>Data Administrasi Surat</h2>
                        <p>Identitas registrasi naskah persuratan sesuai buku agenda</p>
                    </div>
                </div>

                <div class="ubs-fields">
                    @if ($masuk)
                        {{-- No. Agenda: dibuat sistem, hanya tampil --}}
                        <div class="ubs-field">
                            <div class="ubs-labelrow">
                                <label for="nomor_agenda">No. Agenda</label>
                                <span class="ubs-sistem">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                                    Sistem
                                </span>
                            </div>
                            <div class="ubs-input">
                                <input type="text" id="nomor_agenda" value="{{ $surat->nomor_agenda }}" disabled aria-describedby="hint-nomor_agenda">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            </div>
                            <p class="ubs-hint" id="hint-nomor_agenda">Dibuat otomatis: nomor urut/bulan/tahun</p>
                        </div>
                    @endif

                    @foreach ($medan as $m)
                        @include('surat._isian', ['m' => $m])
                    @endforeach
                </div>

                @if ($masuk)
                    <div class="ubs-sec">
                        <span class="ubs-no">2</span>
                        <div>
                            <h2>Instruksi &amp; Alur Disposisi</h2>
                            <p>Penetapan arahan pimpinan dan tindak lanjut surat</p>
                        </div>
                    </div>

                    <div class="ubs-fields">
                        @foreach ($medanDisposisi as $m)
                            @include('surat._isian', ['m' => $m])
                        @endforeach
                    </div>

                    <div class="ubs-chips">
                        <span>Pilihan Cepat:</span>
                        <button type="button" class="ubs-chip" data-sisip="Tanggapi dan selesaikan.">+ Tanggapi &amp; selesaikan</button>
                        <button type="button" class="ubs-chip" data-sisip="Hadir mewakili.">+ Hadir mewakili</button>
                        <button type="button" class="ubs-chip" data-sisip="Koordinasikan dengan bidang terkait.">+ Koordinasi bidang</button>
                    </div>
                @endif

                <div class="ubs-aksi">
                    <a href="{{ route('surat.index', ['jenis' => $jenis]) }}" class="ubs-btn-batal">Batal</a>
                    <button type="submit" class="ubs-btn-simpan">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                        Simpan Perubahan
                    </button>
                </div>

                <div class="ubs-jejak">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    <span>
                        Dicatat oleh <b>{{ $namaPembuat }}</b> pada {{ $tgl($surat->created_at, 'd/m/Y H:i') }}@if ($pernahDisunting); terakhir disunting {{ $tgl($surat->updated_at, 'd/m/Y H:i') }}@endif
                    </span>
                </div>
            </form>
        </section>

        {{-- Kolom kanan --}}
        <aside class="ubs-aside" aria-label="Informasi pendukung">

            @if ($masuk)
                <section class="ubs-card">
                    <h3>Lampiran Berkas Digital</h3>
                    @if ($surat->lampiran)
                        <p class="ubs-kosong"><a href="{{ asset('storage/' . $surat->lampiran) }}" target="_blank" rel="noopener"><b>{{ basename($surat->lampiran) }}</b></a></p>
                    @else
                        <p class="ubs-kosong">Belum ada berkas digital yang tertaut pada surat ini.</p>
                    @endif
                </section>
            @endif

            <section class="ubs-card">
                <h3>{{ $masuk ? 'Jalur Disposisi Naskah' : 'Jejak Surat Keluar' }}</h3>
                <ol class="ubs-tl">
                    @foreach ($langkah as [$judul, $sub, $waktu, $status])
                        <li class="{{ $status }}">
                            <span class="ubs-titik" aria-hidden="true">
                                @if ($status === 'selesai')
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
                                @endif
                            </span>
                            <strong>{{ $judul }}</strong>
                            <span>{{ $sub }}</span>
                            @if ($waktu)<small>{{ $waktu }}@if ($status === 'aktif') (Aktif)@endif</small>@endif
                        </li>
                    @endforeach
                </ol>
            </section>

            <div class="ubs-info">
                <strong>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                    Petunjuk Perubahan Surat
                </strong>
                Perubahan nomor surat atau asal instansi memerlukan verifikasi ulang naskah fisik asli.
                Pastikan berkas pindaan (scan) tetap sinkron dengan tanggal penerimaan.
            </div>
        </aside>
    </div>
</div>

<script>
(function () {
    // Penghitung karakter perihal
    var perihal = document.getElementById('perihal');
    var hitung  = document.getElementById('hitung-perihal');
    if (perihal && hitung) {
        var perbarui = function () { hitung.textContent = perihal.value.length + ' karakter'; };
        perihal.addEventListener('input', perbarui);
        perbarui();
    }

    // Pilihan cepat: menambahkan teks ke arahan disposisi
    var disposisi = document.getElementById('disposisi');
    document.querySelectorAll('[data-sisip]').forEach(function (tombol) {
        tombol.addEventListener('click', function () {
            if (!disposisi) return;
            var teks  = tombol.getAttribute('data-sisip');
            var lama  = disposisi.value.replace(/\s+$/, '');
            disposisi.value = lama ? lama + ' ' + teks : teks;
            disposisi.focus();
        });
    });
})();
</script>
@endsection
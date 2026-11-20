{{--
    resources/views/surat/index.blade.php
    Halaman daftar surat. T-75 (B-039): kolom pencarian dan filter tanggal ada di bagian filter di bawah.
    Data dari PencarianSuratController::cari() (T-74), GET /surat (surat.index).

    Variabel: $surat (paginator; item: jenis, id, nomor_surat, tanggal, pihak, perihal),
              $pesan ('Tidak ada surat yang sesuai' bila kosong, selain itu null)
--}}
@extends('layouts.admin')

@section('title', 'Daftar Surat')

@php
    $labelJenis = [
        'masuk'     => 'Surat Masuk',
        'keluar'    => 'Surat Keluar',
        'keputusan' => 'Surat Keputusan',
    ];
        $ikon = [
        ''          => 'list',
        'masuk'     => 'inbox',
        'keluar'    => 'send',
        'keputusan' => 'file-check',
    ];
    $tab = [
        'masuk'     => 'Surat Masuk',
        'keluar'    => 'Surat Keluar',
        'keputusan'  => 'Keputusan',
    ];
    $jenisAktif  = (string) request('jenis', '');
    $adaPaginasi = method_exists($surat, 'total');
    $total       = $adaPaginasi ? $surat->total() : $surat->count();
    $awal        = $adaPaginasi ? ($surat->firstItem() ?? 0) : ($total ? 1 : 0);
    $akhir       = $adaPaginasi ? ($surat->lastItem() ?? 0) : $total;
@endphp

@push('styles')
<style>
    .srt-garis { height: 4px; background: linear-gradient(90deg, #a5471f 0 33.33%, #f58b5e 33.33% 66.66%, #2e5b2a 66.66% 100%); }
    .srt-area { padding: 0 20px 16px; background: #fff6f1; }
    .srt-statistik {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 12px;
        width: 100%;
        max-width: 1100px;
        margin: 0 auto 10px;
        padding: 0 12px;
        box-sizing: border-box;
    }
    .srt-stat-card {
        width: 100%;
        min-width: 0;
        max-width: none;
        min-height: 62px;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        box-sizing: border-box;
        background: #fff;
        border: 1px solid #f1d5c7;
        border-radius: 11px;
        box-shadow: 0 2px 5px rgba(60,35,20,.12);
    }
    .srt-stat-ikon {
        width: 36px; height: 36px; flex: 0 0 36px;
        display: grid; place-items: center; border-radius: 9px;
    }
    .srt-stat-ikon svg { width: 19px; height: 19px; }
    .srt-stat-ikon.masuk { color: #a5471f; background: #fbe5d9; }
    .srt-stat-ikon.disposisi { color: #245d26; background: #b9efad; }
    .srt-stat-ikon.tindak { color: #8d3812; background: #fbe5d9; }
    .srt-stat-label {
        margin: 0 0 3px; font-size: 9px; letter-spacing: .06em;
        font-weight: 700; color: #82766c; text-transform: uppercase;
    }
    .srt-stat-angka { margin: 0; font-size: 16px; line-height: 1.2; font-weight: 700; color: #3a2a22; }
    .srt-stat-angka small { font-size: 12px; font-weight: 600; }
    @media (max-width: 640px) {
        .srt-statistik { gap: 6px; padding: 0 6px; }
        .srt-stat-card { flex: 1 1 0; min-width: 0; padding: 8px 5px; gap: 5px; }
        .srt-stat-ikon { width: 28px; height: 28px; flex: 0 0 28px; }
        .srt-stat-label { font-size: 8px; }
    }

    .srt-tab { display: flex; gap: 4px; margin: 0 -20px 14px; padding: 0 20px; border-bottom: 1px solid #f1d5c7; }
    .srt-tab a {
        min-height: 48px; padding: 0 16px; display: inline-flex; align-items: center; gap: 8px; white-space: nowrap;
        color: #3a2a22; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -1px;
    }
    .srt-tab a:hover { color: #8d3812; }
    .srt-tab a[aria-current="page"] { color: #a5471f; font-weight: 600; border-bottom-color: #a5471f; }
    .srt-tab a:focus-visible { outline: 2px solid #c2653d; outline-offset: -2px; }
    .srt-jml { min-width: 24px; padding: 1px 8px; font-size: 12px; text-align: center; color: #8d3812; background: #ffdbc8; border-radius: 999px; }
    .akun-panel .akun-tabel-wrap { overflow-x: auto; }
    .srt-tabel th, .srt-tabel td { vertical-align: middle; }
    .srt-tabel td.srt-nomor { text-align: center; }
    .srt-tabel td.srt-utama { font-weight: 600; }

    .srt-flash { margin: 0 0 14px; padding: 12px 16px; color: #20491a; background: #e8f3e5; border: 1px solid #b9d9b2; border-radius: 8px; }
    .srt-flash-galat { color: #8d3812; background: #fdf1ea; border-color: #f1d5c7; }
    .srt-btn-ubah { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; color: #a5471f; background: transparent; border: 0; border-radius: 8px; text-decoration: none; }
    .srt-btn-ubah:hover { background: #fdf1ea; }
    .srt-btn-ubah:focus-visible { outline: 2px solid #c2653d; outline-offset: 2px; }

    /* Layar kecil: tab bisa digeser, baris tabel menjadi kartu per surat */
    @media (max-width: 768px) {
        .srt-tab { overflow-x: auto; }
        .srt-tabel thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
        .srt-tabel, .srt-tabel tbody, .srt-tabel tr, .srt-tabel td { display: block; width: 100%; }
        .srt-tabel tr { padding: 12px 16px; border-bottom: 1px solid #ecd9cf; }
        .srt-tabel td { display: flex; justify-content: space-between; gap: 16px; padding: 5px 0; border: 0; text-align: right; }
        .srt-tabel td::before { content: attr(data-label); flex: 0 0 38%; font-weight: 600; color: #877368; text-align: left; }
        .srt-tabel td.srt-nomor { display: none; }
        .srt-tabel td[colspan] { display: block; text-align: center; }
        .srt-tabel td[colspan]::before { content: none; }
    }
</style>
@endpush

@section('content')
<div class="content akun-page">

    <div class="bidang-header-card">
        <div class="bidang-header">
            <div>
                <h1>Daftar Surat</h1>
                <p>Cari surat masuk, surat keluar, dan surat keputusan.</p>
            </div>
        </div>
    </div>

    <section class="srt-statistik" aria-label="Statistik persuratan">
        <article class="srt-stat-card">
            <span class="srt-stat-ikon masuk" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h14v18H5z"/><path d="M8 7h8M8 11h8M12 15v3M9 16.5l3 1.5 3-1.5"/></svg>
            </span>
            <div><p class="srt-stat-label">Total Masuk Bulan Ini</p><p class="srt-stat-angka">{{ $totalMasukBulanIni ?? 0 }} <small>Surat</small></p></div>
        </article>
        <article class="srt-stat-card">
            <span class="srt-stat-ikon disposisi" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
            </span>
            <div><p class="srt-stat-label">Sudah Disposisi</p><p class="srt-stat-angka">{{ $sudahDisposisi ?? 0 }} <small>Surat</small></p></div>
        </article>
        <article class="srt-stat-card">
            <span class="srt-stat-ikon tindak" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h8l4 4v14H5V3z"/><path d="M15 3v5h4M8 12h8M8 16h3"/><circle cx="17" cy="17" r="3"/><path d="M17 15.5v1.5l1 1"/></svg>
            </span>
            <div><p class="srt-stat-label">Menunggu Tindak Lanjut</p><p class="srt-stat-angka">{{ $belumDisposisi ?? 0 }} <small>Surat</small></p></div>
        </article>
    </section>

    <div class="akun-panel">

        <div class="srt-garis" aria-hidden="true"></div>

        <div class="srt-area">
                    @if (session('sukses'))
                        <div class="srt-flash" role="status">{{ session('sukses') }}</div>
                    @endif
                    @if (session('galat'))
                        <div class="srt-flash srt-flash-galat" role="alert">{{ session('galat') }}</div>
                    @endif

            {{-- Tab jenis surat (mengisi parameter jenis; filter lain ikut terbawa).
                 Angka jumlah tampil bila controller mengirim $jumlahJenis, mis. ['masuk' => 24, 'keluar' => 18, 'keputusan' => 6]. --}}
            <nav class="srt-tab" aria-label="Jenis surat">
                @foreach ($tab as $nilai => $label)
                    <a href="{{ route('surat.index', array_filter(array_merge(request()->except(['jenis', 'page']), ['jenis' => $nilai]), fn ($v) => $v !== null && $v !== '')) }}"
                       @if ($jenisAktif === (string) $nilai) aria-current="page" @endif>
                        <x-icon :name="$ikon[$nilai]" :size="18" />
                        {{ $label }}
                        @if (isset($jumlahJenis[$nilai]))<span class="srt-jml">{{ $jumlahJenis[$nilai] }}</span>@endif
                    </a>
                @endforeach
            </nav>

            {{-- Kolom pencarian dan filter tanggal (T-75) --}}
            @php
    $nilaiQ      = old('q', request('q'));
    $nilaiDari   = old('dari', request('dari'));
    $nilaiSampai = old('sampai', request('sampai'));
    $jenisAktif  = request('jenis');
@endphp

@push('styles')
<style>
    .srt-filter {
        display: flex; flex-wrap: wrap; align-items: flex-start; gap: 10px;
        padding: 14px 16px; background: #fff; border: 1px solid #f1d5c7; border-radius: 12px;
    }
    .srt-cari {
        flex: 0 1 220px; max-width: 220px; min-height: 34px; display: flex; align-items: center; gap: 8px;
        padding: 0 12px; color: #877368; background: #fff; border: 1px solid #f1d5c7; border-radius: 8px;
    }
    .srt-cari:focus-within { outline: 2px solid #c2653d; outline-offset: 2px; }
    .srt-cari input {
        flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font: inherit; color: #3a2a22;
    }
    .srt-tanggal { display: flex; align-items: flex-start; gap: 8px; }
    .srt-tanggal .srt-sd { min-height: 34px; display: flex; align-items: center; font-size: 13px; font-weight: 600; color: #3a2a22; }
    .srt-tanggal input[type="date"] {
        min-height: 34px; padding: 0 9px; font: inherit; color: #3a2a22;
        background: #fff; border: 1px solid #f1d5c7; border-radius: 8px;
    }
    .srt-tanggal input[aria-invalid="true"], .srt-cari input[aria-invalid="true"] { border-color: #8d3812; }
    .srt-galat { margin: 0; font-size: 13px; color: #8d3812; }
    .srt-galat-baris { display: flex; flex-wrap: wrap; gap: 4px 16px; margin-top: 10px; padding: 0 4px; }
    .srt-btn-terapkan {
        min-height: 34px; padding: 0 12px; display: inline-flex; align-items: center; gap: 6px;
        font: inherit; font-size: 12px; font-weight: 600; cursor: pointer; color: #fff; background: #20491a;
        border: 0; border-radius: 8px;
    }
    .srt-btn-terapkan:hover { background: #183a14; }
    .srt-btn-ulang {
        width: 34px; min-height: 34px; display: inline-grid; place-items: center;
        color: #3a2a22; background: #fff; border: 1px solid #f1d5c7; border-radius: 8px;
    }
    .srt-btn-ulang:hover { background: #fdf1ea; }
    .srt-aksi { margin-left: auto; display: flex; align-items: center; gap: 10px; }
    .srt-filter input[type="date"]:focus-visible, .srt-btn-terapkan:focus-visible, .srt-btn-ulang:focus-visible {
        outline: 2px solid #c2653d; outline-offset: 2px;
    }
    .srt-btn-catat {
        min-height: 34px; padding: 0 12px; display: inline-flex; align-items: center; gap: 6px;
        font-size: 13px; font-weight: 600; color: #fff; text-decoration: none; background: #8d3812; border-radius: 7px;
    }
    .srt-btn-catat:hover { background: #7a2f0e; }
    .srt-btn-catat:focus-visible { outline: 2px solid #c2653d; outline-offset: 2px; }
    @media (max-width: 640px) {
        .srt-btn-catat { width: 100%; justify-content: center; }
        .srt-cari { flex-basis: 100%; max-width: none; }
        .srt-tanggal { flex: 1 1 100%; }
        .srt-tanggal > div { flex: 1; }
        .srt-tanggal input[type="date"] { width: 100%; }
        .srt-btn-terapkan { flex: 1; justify-content: center; }
        .srt-aksi { margin-left: 0; flex: 1 1 100%; }
    }
</style>
@endpush

<form class="srt-filter" method="GET" action="{{ route('surat.index') }}" role="search">
    @if ($jenisAktif)
        <input type="hidden" name="jenis" value="{{ $jenisAktif }}">
    @endif

    <label class="srt-cari">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="search" name="q" value="{{ $nilaiQ }}" maxlength="100"
               placeholder="Cari perihal surat..." aria-label="Cari perihal surat"
               @if ($errors->has('q')) aria-invalid="true" aria-describedby="galat-q" @endif>
    </label>

    <div class="srt-tanggal">
        <div>
            <input type="date" name="dari" value="{{ $nilaiDari }}" aria-label="Dari tanggal"
                   @if ($errors->has('dari')) aria-invalid="true" aria-describedby="galat-dari" @endif>
        </div>
        <span class="srt-sd" aria-hidden="true">s/d</span>
        <div>
            <input type="date" name="sampai" value="{{ $nilaiSampai }}" aria-label="Sampai tanggal"
                   @if ($errors->has('sampai')) aria-invalid="true" aria-describedby="galat-sampai" @endif>
        </div>
    </div>

    <button type="submit" class="srt-btn-terapkan">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
        Terapkan
    </button>

    <a href="{{ route('surat.index', array_filter(['jenis' => $jenisAktif])) }}" class="srt-btn-ulang" aria-label="Atur ulang pencarian" title="Atur ulang">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
    </a>

    <div class="srt-aksi">
        <a href="{{ route('surat.catat-masuk') }}" class="srt-btn-catat">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Catat Surat Masuk
        </a>
    </div>
</form>

@if ($errors->hasAny(['q', 'dari', 'sampai']))
    <div class="srt-galat-baris">
        @foreach (['q', 'dari', 'sampai'] as $kolom)
            @error($kolom)
                <p class="srt-galat" id="galat-{{ $kolom }}" role="alert">{{ $message }}</p>
            @enderror
        @endforeach
    </div>
@endif

        </div>

        {{-- Tabel --}}
        <div class="akun-tabel-wrap">
            <table class="akun-tabel srt-tabel">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Jenis</th>
                        <th>Nomor Surat</th>
                        <th>Tanggal</th>
                        <th>Asal / Tujuan</th>
                        <th>Perihal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($surat as $s)
                        <tr>
                            <td class="srt-nomor">{{ $adaPaginasi ? $surat->firstItem() + $loop->index : $loop->iteration }}</td>
                            <td data-label="Jenis"><span class="akun-pill">{{ $labelJenis[$s->jenis] ?? $s->jenis }}</span></td>
                            <td data-label="Nomor Surat" class="srt-utama">{{ $s->nomor_surat ?? '-' }}</td>
                            <td data-label="Tanggal">{{ $s->tanggal ? \Carbon\Carbon::parse($s->tanggal)->format('d/m/Y') : '-' }}</td>
                            <td data-label="Asal / Tujuan">{{ $s->pihak ?? '-' }}</td>
                            <td data-label="Perihal">{{ $s->perihal ?? '-' }}</td>
                            <td data-label="Aksi">
                                @if (in_array($s->jenis, ['masuk', 'keluar'], true))
                                    <a href="{{ route('surat.edit', [$s->jenis, $s->id]) }}" class="srt-btn-ubah"
                                        aria-label="Ubah surat {{ $s->nomor_surat }}" title="Ubah">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="bidang-kosong">{{ $pesan ?? 'Tidak ada surat yang sesuai' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="akun-footer">
            <span>Menampilkan <b>{{ $awal }}&ndash;{{ $akhir }}</b> dari <b>{{ $total }}</b> surat</span>

            @if ($adaPaginasi && $surat->hasPages())
                <nav class="akun-page-nav" aria-label="Paginasi">
                    @if ($surat->onFirstPage())
                        <span class="nav mati">&lsaquo; Sebelumnya</span>
                    @else
                        <a class="nav" href="{{ $surat->previousPageUrl() }}">&lsaquo; Sebelumnya</a>
                    @endif

                    @foreach ($surat->getUrlRange(1, $surat->lastPage()) as $halaman => $url)
                        <a class="num {{ $halaman === $surat->currentPage() ? 'aktif' : '' }}" href="{{ $url }}"
                           @if ($halaman === $surat->currentPage()) aria-current="page" @endif>{{ $halaman }}</a>
                    @endforeach

                    @if ($surat->hasMorePages())
                        <a class="nav" href="{{ $surat->nextPageUrl() }}">Selanjutnya &rsaquo;</a>
                    @else
                        <span class="nav mati">Selanjutnya &rsaquo;</span>
                    @endif
                </nav>
            @endif
        </div>
    </div>
</div>
@endsection
{{--
    resources/views/admin/anggota/index.blade.php
    Tampilan daftar anggota sesuai Figma "Kelola Anggota - Daftar Anggota".
    Catatan: fitur filter/cari/paginasi sungguhan dikerjakan di B-020 (bukan T-27).

    Variabel yang dipakai (semua opsional kecuali $anggota):
      $anggota       : koleksi atau paginator. Tiap item: nama_lengkap, nama_panggilan, nomor_telepon,
                       nama_bidang, posisi, status ('aktif'/'nonaktif'), lengkap (bool)
      $daftarPeran   : daftar peran untuk filter (teks, atau objek dengan nama_peran)
      $daftarBidang  : daftar bidang untuk filter (teks, atau objek dengan nama_bidang)
--}}
@extends('layouts.admin')

@section('title', 'Kelola Anggota')

@php
    // Terima teks biasa ATAU objek baris tabel (nama_peran / nama_bidang / nama).
    $ambilNama = fn ($item, array $kolom) => is_object($item)
        ? (collect($kolom)->map(fn ($k) => $item->{$k} ?? null)->filter()->first() ?? '')
        : (string) $item;
    $daftarPeran  = collect($daftarPeran ?? [])->map(fn ($i) => $ambilNama($i, ['nama_peran', 'nama']))->filter()->unique()->values();
    $daftarBidang = collect($daftarBidang ?? [])->map(fn ($i) => $ambilNama($i, ['nama_bidang', 'nama']))->filter()->unique()->values();
    $adaPaginasi  = method_exists($anggota, 'total');
    $total        = $adaPaginasi ? $anggota->total() : $anggota->count();
    $dari         = $adaPaginasi ? ($anggota->firstItem() ?? 0) : ($total ? 1 : 0);
    $sampai       = $adaPaginasi ? ($anggota->lastItem() ?? 0) : $total;
    $inisial = function ($nama) {
        $kata = preg_split('/\s+/', trim((string) $nama), -1, PREG_SPLIT_NO_EMPTY);
        return mb_strtoupper(mb_substr($kata[0] ?? '', 0, 1) . mb_substr($kata[1] ?? '', 0, 1));
    };
@endphp

@push('styles')
<style>
    .agl-aksi { display: flex; align-items: center; justify-content: center; gap: 10px; }
    .agl-aksi a { width: 32px; height: 32px; display: grid; place-items: center; border-radius: 8px; color: var(--ink); }
    .agl-aksi a:hover { background: #fdf1ea; color: var(--coklat-tua); }
    .agl-aksi a:focus-visible, .agl-btn-cari:focus-visible { outline: 2px solid #c2653d; outline-offset: 2px; }
    .agl-belum { background: #ffe3c7; color: #9a4a14; }
    .agl-tabel th, .agl-tabel td { vertical-align: middle; }
    .agl-tabel td.agl-nomor { text-align: center; }
    .agl-tabel .akun-username { margin-top: 1px; }
    .agl-posisi { max-width: 120px; }
</style>
@endpush

@section('content')
<div class="content akun-page">

    {{-- Kepala halaman --}}
    <div class="bidang-header-card">
        <div class="bidang-header">
            <div>
                <h1>Kelola Anggota</h1>
                <p>Kelola data anggota pengguna DWP-IS Kemenkeu.</p>
            </div>
            <a href="{{ route('admin.anggota.create') }}" class="bidang-btn-tambah">
                <span aria-hidden="true">+</span> Tambah Anggota
            </a>
        </div>
    </div>

    {{-- Pesan sukses (Figma: "Berhasil Ditambahkan"). Menerima kunci 'success' maupun 'sukses'. --}}
    @php $pesanSukses = session('success') ?? session('sukses'); @endphp
    @if ($pesanSukses)
        <div class="akun-alert" id="agl-sukses" role="status">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
            <strong>{{ $pesanSukses }}</strong>
            <button type="button" class="akun-alert-tutup" id="agl-sukses-tutup" aria-label="Tutup pesan">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    <div class="akun-panel">

        {{-- Filter dan pencarian --}}
        <form class="akun-filter" method="GET" action="{{ route('admin.anggota.index') }}">
            <div class="akun-filter-grup">
                <label for="f-peran">Filter Peran</label>
                <select id="f-peran" name="peran">
                    <option value="">Semua Peran</option>
                    @foreach ($daftarPeran as $p)
                        <option value="{{ $p }}" @selected(request('peran') === $p)>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div class="akun-filter-grup">
                <label for="f-bidang">Filter Bidang</label>
                <select id="f-bidang" name="bidang">
                    <option value="">Semua Bidang</option>
                    @foreach ($daftarBidang as $b)
                        <option value="{{ $b }}" @selected(request('bidang') === $b)>{{ $b }}</option>
                    @endforeach
                </select>
            </div>
            <a href="{{ route('admin.anggota.index') }}" class="akun-btn-reset">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v9"/><path d="M18.4 6.6a9 9 0 1 1-12.8 0"/></svg>
                Atur Ulang
            </a>
            <div class="akun-cari">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau username..." aria-label="Cari anggota">
            </div>
        </form>

        {{-- Tabel --}}
        <div class="akun-tabel-wrap">
            <table class="akun-tabel agl-tabel">
                <thead>
                    <tr>
                        <th>NO</th>
                        <th>NAMA LENGKAP</th>
                        <th>NOMOR TELEPON</th>
                        <th>BIDANG</th>
                        <th>POSISI</th>
                        <th>STATUS</th>
                        <th>KELENGKAPAN DATA</th>
                        <th class="kolom-aksi">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($anggota as $a)
                        @php $aktif = ($a->status ?? 'aktif') === 'aktif'; @endphp
                        <tr>
                            <td class="agl-nomor">{{ $adaPaginasi ? $anggota->firstItem() + $loop->index : $loop->iteration }}</td>
                            <td>
                                <div class="akun-nama">
                                    <span class="akun-inisial">{{ $inisial($a->nama_lengkap) }}</span>
                                    <div>
                                        <strong>{{ $a->nama_lengkap }}</strong>
                                        @if (!empty($a->nama_panggilan))
                                            <div class="akun-username">{{ $a->nama_panggilan }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>{{ $a->nomor_telepon }}</td>
                            <td><span class="akun-pill">{{ $a->nama_bidang ?? '-' }}</span></td>
                            <td class="agl-posisi">{{ $a->posisi ?? '-' }}</td>
                            <td>
                                <span class="akun-status {{ $aktif ? 'aktif' : 'nonaktif' }}"><i></i>{{ $aktif ? 'Aktif' : 'Nonaktif' }}</span>
                            </td>
                            <td>
                                @if (!empty($a->lengkap))
                                    <span class="akun-status aktif">Lengkap</span>
                                @else
                                    <span class="akun-status agl-belum">Belum Lengkap</span>
                                @endif
                            </td>
                            <td>
                                <div class="agl-aksi">
                                    <a href="#" aria-label="Lihat detail {{ $a->nama_lengkap }}" title="Lihat detail">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <a href="#" aria-label="Ubah {{ $a->nama_lengkap }}" title="Ubah">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="bidang-kosong">Tidak ada anggota yang sesuai.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer dan paginasi --}}
        <div class="akun-footer">
            <span>Menampilkan <b>{{ $dari }}–{{ $sampai }}</b> dari <b>{{ $total }}</b> akun</span>

            @if ($adaPaginasi && $anggota->hasPages())
                <nav class="akun-page-nav" aria-label="Paginasi">
                    @if ($anggota->onFirstPage())
                        <span class="nav mati">‹ Sebelumnya</span>
                    @else
                        <a class="nav" href="{{ $anggota->previousPageUrl() }}">‹ Sebelumnya</a>
                    @endif

                    @foreach ($anggota->getUrlRange(1, $anggota->lastPage()) as $halaman => $url)
                        <a class="num {{ $halaman === $anggota->currentPage() ? 'aktif' : '' }}" href="{{ $url }}"
                           @if ($halaman === $anggota->currentPage()) aria-current="page" @endif>{{ $halaman }}</a>
                    @endforeach

                    @if ($anggota->hasMorePages())
                        <a class="nav" href="{{ $anggota->nextPageUrl() }}">Selanjutnya ›</a>
                    @else
                        <span class="nav mati">Selanjutnya ›</span>
                    @endif
                </nav>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var tutup = document.getElementById('agl-sukses-tutup');
    var kotak = document.getElementById('agl-sukses');
    if (tutup && kotak) {
        tutup.addEventListener('click', function () { kotak.hidden = true; });
    }
})();
</script>
@endpush
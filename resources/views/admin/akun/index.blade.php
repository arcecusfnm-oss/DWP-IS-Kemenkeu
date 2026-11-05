@extends('layouts.admin')

@section('content')
@php
    $fPeran  = request('peran');
    $fBidang = request('bidang');
    $fCari   = trim(request('q', ''));

    $jumlahFilter = collect([$fPeran, $fBidang, $fCari])->filter()->count();
    $adaFilter    = $jumlahFilter > 0;
@endphp

<div class="content akun-page">

    {{-- Header --}}
    <div class="bidang-header-card">
        <div class="bidang-header">
            <div>
                <h1>Kelola Akun</h1>
                <p>Kelola akun pengguna DWP-IS Kemenkeu.</p>
            </div>
            <a href="{{ route('admin.akun.create') }}" class="bidang-btn-tambah">
                <span>+</span> Buat Akun
            </a>
        </div>
    </div>

    {{-- Alert sukses (demo: /admin/akun?sukses=1) --}}
    @if (request('sukses') || session('sukses'))
        <div class="akun-alert" id="akunAlert">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
            <strong>{{ session('sukses', 'Data berhasil disimpan.') }}</strong>
            <button type="button" class="akun-alert-tutup" onclick="document.getElementById('akunAlert').remove()" aria-label="Tutup">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    {{-- Kartu statistik --}}
    <div class="akun-stats">
        <div class="akun-stat">
            <div class="akun-stat-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div><small>TOTAL AKUN</small><b class="serif">{{ number_format($stat['total'], 0, ',', '.') }} Akun</b></div>
        </div>
        <div class="akun-stat">
            <div class="akun-stat-icon hijau">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
            </div>
            <div><small>AKUN AKTIF</small><b class="serif">{{ number_format($stat['aktif'], 0, ',', '.') }} Akun</b></div>
        </div>
        <div class="akun-stat">
            <div class="akun-stat-icon merah">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m17 8 5 5M22 8l-5 5"/></svg>
            </div>
            <div><small>NONAKTIF</small><b class="serif">{{ number_format($stat['nonaktif'], 0, ',', '.') }} Akun</b></div>
        </div>
        <div class="akun-stat">
            <div class="akun-stat-icon biru">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4M12 16h.01"/></svg>
            </div>
            <div><small>HAK ADMIN</small><b class="serif">{{ number_format($stat['admin'], 0, ',', '.') }} Petugas</b></div>
        </div>
    </div>

    {{-- Tabel + filter --}}
    <div class="akun-panel">
        <form method="GET" action="{{ route('admin.akun.index') }}" class="akun-filter">
            <div class="akun-filter-grup">
                <label for="fPeran">Filter Peran</label>
                <select name="peran" id="fPeran" onchange="this.form.submit()">
                    <option value="">Semua Peran</option>
                    @foreach ($daftarPeran as $p)
                        <option value="{{ $p }}" @selected($fPeran === $p)>{{ $p }}</option>
                    @endforeach
                </select>
            </div>

            <div class="akun-filter-grup">
                <label for="fBidang">Filter Bidang</label>
                <select name="bidang" id="fBidang" onchange="this.form.submit()">
                    <option value="">Semua Bidang</option>
                    @foreach ($daftarBidang as $b)
                        <option value="{{ $b }}" @selected($fBidang === $b)>{{ $b }}</option>
                    @endforeach
                </select>
            </div>

            <a href="{{ route('admin.akun.index') }}" class="akun-btn-reset {{ $adaFilter ? 'aktif' : '' }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>
                Atur Ulang
            </a>

            @if ($adaFilter)
                <div class="akun-filter-info">
                    Filter Aktif: <span>{{ $jumlahFilter }} Kriteria</span>
                </div>
            @else
                <div class="akun-cari">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama atau username...">
                </div>
            @endif
        </form>

        @if ($akun->isEmpty())
            {{-- Empty state --}}
            <div class="akun-kosong">
                <div class="akun-kosong-ikon">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h7M3 12h5M3 18h7"/><circle cx="16" cy="14" r="4"/><path d="m21 19-2.5-2.5"/></svg>
                </div>
                <h3 class="serif">Tidak ada akun yang sesuai dengan filter</h3>
                <p>
                    Tidak ditemukan data pengguna
                    @if ($fPeran) untuk peran <b>{{ $fPeran }}</b>@endif
                    @if ($fBidang) pada bidang <b>{{ $fBidang }}</b>@endif
                    @if ($fCari) dengan kata kunci <b>{{ request('q') }}</b>@endif.
                    Cobalah melonggarkan filter atau atur ulang pencarian.
                </p>
                <div class="akun-kosong-aksi">
                    <a href="{{ route('admin.akun.index') }}" class="akun-btn-ghost">Atur Ulang Filter</a>
                    <a href="#" class="akun-link-panduan">Panduan Kelola Akun</a>
                </div>
            </div>
        @else
            <div class="akun-tabel-wrap">
                <table class="akun-tabel">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Lengkap</th>
                            <th>Nama Pengguna</th>
                            <th>Peran</th>
                            <th>Bidang</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($akun as $i => $a)
                            @php
                                $inisial = collect(explode(' ', $a->nama_lengkap))->take(2)
                                    ->map(fn ($k) => strtoupper(substr($k, 0, 1)))->implode('');
                            @endphp
                            <tr>
                                <td>{{ $akun->firstItem() + $i }}</td>
                                <td>
                                    <div class="akun-nama">
                                        <span class="akun-inisial">{{ $inisial }}</span>
                                        <strong>{{ $a->nama_lengkap }}</strong>
                                    </div>
                                </td>
                                <td class="akun-username">{{ $a->nama_pengguna }}</td>
                                <td><span class="akun-pill">{{ $a->nama_peran }}</span></td>
                                <td>{{ $a->nama_bidang }}</td>
                                <td>
                                    <span class="akun-status {{ $a->status === 'aktif' ? 'aktif' : 'nonaktif' }}">
                                        <i></i>{{ ucfirst($a->status) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="akun-aksi">
                                        <a href="{{ route('admin.akun.edit', $a->id_pengguna) }}" title="Edit">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                        </a>
                                        <a href="#" title="Ubah peran">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4"/><circle cx="18" cy="18" r="2"/><path d="M18 14v1M18 21v1M21.5 16l-.9.5M15.4 19.5l-.9.5M21.5 20l-.9-.5M15.4 16.5l-.9-.5"/></svg>
                                        </a>
                                        <a href="#" title="Reset kata sandi">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.3-9.3M17 6l3 3M14 9l2 2"/></svg>
                                        </a>
                                        <button type="button" title="Hapus" class="hapus">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Footer / pagination --}}
        <div class="akun-footer">
            <span>Menampilkan <b>{{ $akun->firstItem() ?? 0 }}-{{ $akun->lastItem() ?? 0 }}</b> dari <b>{{ $akun->total() }}</b> akun</span>

            @if ($akun->hasPages())
                <div class="akun-page-nav">
                    @if ($akun->onFirstPage())
                        <span class="nav mati">‹ Sebelumnya</span>
                    @else
                        <a href="{{ $akun->previousPageUrl() }}" class="nav">‹ Sebelumnya</a>
                    @endif

                    @foreach ($akun->getUrlRange(1, $akun->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="num {{ $page == $akun->currentPage() ? 'aktif' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($akun->hasMorePages())
                        <a href="{{ $akun->nextPageUrl() }}" class="nav">Selanjutnya ›</a>
                    @else
                        <span class="nav mati">Selanjutnya ›</span>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
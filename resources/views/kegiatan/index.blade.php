@extends('layouts.bidang')

@section('title', 'Kegiatan')

@section('content')
    <div class="content bidang-page">

        {{-- HEADER --}}
        <div class="bidang-header-card">
            <div class="bidang-header">
                <div class="program-header-info">
                    <h1>Kegiatan</h1>
                    <span class="program-bidang-badge">
                        {{ $namaBidang ?? 'Bidang' }}
                    </span>
                </div>

                <a href="{{ route('kegiatan.create') }}" class="bidang-btn-tambah" style="background: #b5532a;">
                    <span>+</span> Tambah Kegiatan
                </a>
            </div>
        </div>

        @if (session('sukses'))
            <div class="bidang-alert bidang-alert-sukses">
                {{ session('sukses') }}
            </div>
        @endif

        @if (session('galat'))
            <div class="bidang-alert">
                {{ session('galat') }}
            </div>
        @endif

        {{-- DAFTAR KEGIATAN --}}
        <div class="bidang-card program-daftar-card">

            <div class="program-card-heading">
                <div>
                    <h2>Daftar Kegiatan</h2>
                    <p>Tahapan pelaksanaan setiap program kerja bidang {{ strtolower($namaBidang ?? 'ini') }}.</p>
                </div>

                <form action="{{ route('kegiatan.index') }}" method="GET" class="kegiatan-filter-form">
                    <div class="program-search">
                        <input type="search" name="cari" value="{{ request('cari') }}"
                               placeholder="Cari kegiatan..." aria-label="Cari kegiatan">
                        <button type="submit">Cari</button>
                    </div>

                    <select name="program" onchange="this.form.submit()" aria-label="Filter Program Kerja"
                            class="kegiatan-filter-select">
                        <option value="">Program Kerja: Semua program</option>
                        @foreach ($daftarProgram as $prog)
                            <option value="{{ $prog->id_program_kerja }}" @selected($programDipilih === (int) $prog->id_program_kerja)>
                                {{ $prog->nama_program }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="bidang-table-wrapper">
                <table class="bidang-table program-table">
                    <thead>
                        <tr>
                            <th style="width: auto; text-align: left;">NAMA KEGIATAN</th>
                            <th>PROGRAM KERJA</th>
                            <th>TANGGAL</th>
                            <th style="width: 220px;">AKSI</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($kegiatan as $item)
                            @php
                                $mulai = \Carbon\Carbon::parse($item->tanggal_mulai);
                                $selesai = \Carbon\Carbon::parse($item->tanggal_selesai);
                                $teksTanggal = $mulai->isSameDay($selesai)
                                    ? $mulai->format('d M Y')
                                    : $mulai->format('d M') . ' - ' . $selesai->format('d M Y');
                            @endphp
                            <tr>
                                <td style="text-align: left;">
                                    <strong>{{ $item->nama_kegiatan }}</strong>
                                </td>
                                <td>{{ $item->nama_program }}</td>
                                <td style="white-space: nowrap;">{{ $teksTanggal }}</td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <button type="button" disabled title="Fitur lihat detail menyusul"
                                                class="program-btn-edit program-btn-disabled"
                                                style="background: #1e2d4a; border-color: #1e2d4a; color: #fff;">
                                            Lihat
                                        </button>
                                        <button type="button" disabled title="Fitur ubah kegiatan menyusul"
                                                class="program-btn-edit program-btn-disabled"
                                                style="background: #c2653d; border-color: #c2653d; color: #fff;">
                                            Edit
                                        </button>
                                        <button type="button" disabled title="Fitur hapus kegiatan menyusul"
                                                class="program-btn-edit program-btn-disabled"
                                                style="background: #7d3115; border-color: #7d3115; color: #fff;">
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="bidang-kosong">
                                    {{ request('cari') || $programDipilih ? 'Kegiatan tidak ditemukan.' : 'Belum ada kegiatan untuk bidang ini.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($kegiatan->hasPages())
                <div class="program-pagination">
                    <span>
                        Menampilkan {{ $kegiatan->firstItem() }}–{{ $kegiatan->lastItem() }}
                        dari {{ $kegiatan->total() }} kegiatan
                    </span>

                    {{ $kegiatan->links() }}
                </div>
            @endif

        </div>
    </div>
@endsection
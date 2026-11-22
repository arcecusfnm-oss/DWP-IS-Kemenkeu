@extends('layouts.bidang')

@section('title', 'Program Kerja')

@section('content')
    @php
        $bolehKelola   = \App\Support\PeranProgramKerja::bolehKelolaProgram(auth()->user());
        $bolehKegiatan = \App\Support\PeranProgramKerja::bolehKelolaKegiatan(auth()->user());
        $adaAksi       = $bolehKelola || $bolehKegiatan;
    @endphp

    <div class="content bidang-page">

        {{-- HEADER --}}
        <div class="bidang-header-card">
            <div class="bidang-header">
                <div class="program-header-info">
                    <h1>Program Kerja</h1>
                    <span class="program-bidang-badge">
                        {{ $namaBidang ?? 'Bidang' }}
                    </span>
                </div>

                @if ($bolehKelola)
                    <a href="{{ route('program-kerja.create') }}" class="bidang-btn-tambah">
                        <span>+</span> Tambah Program Kerja
                    </a>
                @endif
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

        {{-- REKAP ANGGARAN TAHUN BERJALAN --}}
        <div class="bidang-card" style="margin-bottom: 20px; padding: 20px;">
            <h2 style="margin: 0 0 8px;">Total Anggaran {{ $tahunAnggaran ?? now()->year }}</h2>
            <p style="font-size: 1.5rem; font-weight: 700; margin: 0;">
                Rp {{ number_format($totalAnggaran ?? 0, 0, ',', '.') }}
            </p>
        </div>

        {{-- DAFTAR PROGRAM --}}
        <div class="bidang-card program-daftar-card">

            <div class="program-card-heading">
                <div>
                    <h2>Daftar Program Kerja</h2>
                    <p>Daftar program kerja yang dimiliki bidang ini.</p>
                </div>

                <form action="{{ route('program-kerja.bidang-saya') }}" method="GET" class="program-search">
                    <input type="search" name="cari" value="{{ request('cari') }}" placeholder="Cari nama program..."
                        aria-label="Cari nama program">
                    <button type="submit" aria-label="Cari">
                        Cari
                    </button>
                </form>
            </div>

            <div class="bidang-table-wrapper">
                <table class="bidang-table program-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Program</th>
                            <th>Bidang</th>
                            <th>Tanggal Mulai</th>
                            <th>Tanggal Selesai</th>
                            <th>Anggaran</th>
                            <th>Jumlah Kegiatan</th>
                            @if ($adaAksi)
                                <th class="program-col-aksi">Aksi</th>
                            @endif
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($programKerja as $index => $program)
                            <tr>
                                <td>{{ $programKerja->firstItem() + $index }}</td>
                                <td>
                                    <strong>{{ $program->nama_program }}</strong>
                                </td>
                                <td>
                                    <span class="program-bidang-badge">
                                        {{ $namaBidang ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    {{ $program->tanggal_mulai ? \Carbon\Carbon::parse($program->tanggal_mulai)->format('d-m-Y') : '-' }}
                                </td>
                                <td>
                                    {{ $program->tanggal_selesai ? \Carbon\Carbon::parse($program->tanggal_selesai)->format('d-m-Y') : '-' }}
                                </td>
                                <td>
                                    {{ $program->anggaran !== null ? 'Rp ' . number_format($program->anggaran, 0, ',', '.') : '-' }}
                                </td>
                                <td>{{ $program->jumlah_kegiatan ?? 0 }}</td>
                                @if ($adaAksi)
                                    <td class="program-col-aksi">
                                        <div class="program-aksi-group">
                                            @if ($bolehKelola)
                                                <a href="{{ route('program-kerja.edit', ['id' => $program->id_program_kerja]) }}"
                                                   class="program-btn-edit">Ubah</a>
                                            @endif
                                            @if ($bolehKegiatan)
                                                <a href="{{ route('program-kerja.kegiatan.create', ['id' => $program->id_program_kerja]) }}"
                                                   class="program-btn-edit">Kegiatan</a>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $adaAksi ? 8 : 7 }}" class="bidang-kosong">
                                    @if (request('cari'))
                                        Program kerja tidak ditemukan.
                                    @else
                                        <div>Belum ada program kerja untuk bidang ini.</div>
                                        @if ($bolehKelola)
                                            <a href="{{ route('program-kerja.create') }}" class="bidang-btn-tambah" style="display: inline-flex; margin-top: 12px;">
                                                + Tambah Program Kerja
                                            </a>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($programKerja->hasPages())
                <div class="program-pagination">
                    <span>
                        Menampilkan {{ $programKerja->firstItem() }}–{{ $programKerja->lastItem() }}
                        dari {{ $programKerja->total() }} program
                    </span>

                    {{ $programKerja->links() }}
                </div>
            @endif

        </div>
    </div>
@endsection
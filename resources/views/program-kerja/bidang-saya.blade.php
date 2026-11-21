@extends('layouts.bidang')

@section('title', 'Program Kerja')

@section('content')
    @php
        $bolehKelola   = \App\Support\PeranProgramKerja::bolehKelolaProgram(auth()->user());
        $bolehKegiatan = \App\Support\PeranProgramKerja::bolehKelolaKegiatan(auth()->user());
        $adaAksi       = $bolehKelola || $bolehKegiatan;

        $tahunAktif = (int) ($tahunAnggaran ?? now()->year);
        $totalAnggaranTahun = isset($totalAnggaran)
            ? (float) $totalAnggaran
            : (float) $programKerja->getCollection()
                ->filter(fn ($p) => $p->tanggal_mulai && \Carbon\Carbon::parse($p->tanggal_mulai)->year === $tahunAktif)
                ->sum('anggaran');
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
                            <th>Proker / Kegiatan</th>
                            <th>Bidang</th>
                            <th>Tanggal Mulai</th>
                            <th>Tanggal Selesai</th>
                            <th>Status</th>
                            <th>Anggaran</th>
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
                                    <span class="program-bidang-badge">
                                        {{ $program->status ?? 'Diajukan' }}
                                    </span>
                                </td>
                                <td>
                                    {{ $program->anggaran !== null ? 'Rp ' . number_format($program->anggaran, 0, ',', '.') : '-' }}
                                </td>
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
                                    {{ request('cari') ? 'Program kerja tidak ditemukan.' : $pesan ?? 'Belum ada program kerja untuk bidang ini.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    <tfoot>
                        <tr>
                            <td colspan="6"><strong>TOTAL ANGGARAN ({{ $tahunAktif }})</strong></td>
                            <td colspan="{{ $adaAksi ? 2 : 1 }}">
                                <strong>Rp {{ number_format($totalAnggaranTahun, 0, ',', '.') }}</strong>
                            </td>
                        </tr>
                    </tfoot>
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
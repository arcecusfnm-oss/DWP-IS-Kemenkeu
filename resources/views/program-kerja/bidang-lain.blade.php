@extends(\App\Support\PeranProgramKerja::layoutHalaman(auth()->user()))

@section('title', 'Program Kerja Bidang Lain')

@section('content')
    @php
        $layoutBidang = \App\Support\PeranProgramKerja::layoutHalaman(auth()->user()) === 'layouts.bidang';
    @endphp

    @unless ($layoutBidang)
        {{-- Layout admin tidak memuat bidang.css (gaya program-*) --}}
        <link rel="stylesheet" href="{{ asset('css/bidang.css') }}?v={{ filemtime(public_path('css/bidang.css')) }}">
    @endunless

    <div class="content bidang-page">

        {{-- HEADER --}}
        <div class="bidang-header-card">
            <div class="bidang-header">
                <div class="program-header-info">
                    <h1>Program Kerja Bidang Lain</h1>
                    <span class="program-bidang-badge">Hanya baca</span>
                </div>
            </div>
        </div>

        <div class="akun-info-box kecil">
            <x-icon name="list" :size="18" />
            Halaman ini hanya untuk melihat program kerja bidang lain. Program tidak dapat diubah dari sini.
        </div>

        {{-- DAFTAR PROGRAM --}}
        <div class="bidang-card program-daftar-card">

            <div class="program-card-heading">
                <div>
                    <h2>Daftar Program Kerja Bidang Lain</h2>
                    <p>Program kerja dari seluruh bidang selain bidang Anda.</p>
                </div>

                <form action="{{ route('program-kerja.bidang-lain') }}" method="GET" class="program-search">
                    <input type="search" name="cari" value="{{ request('cari') }}" maxlength="100"
                        placeholder="Cari nama program..." aria-label="Cari nama program">
                    <button type="submit" aria-label="Cari">Cari</button>
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
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($programKerja as $index => $program)
                            <tr>
                                <td>{{ $programKerja->firstItem() + $index }}</td>
                                <td><strong>{{ $program->nama_program }}</strong></td>
                                <td>
                                    <span class="program-bidang-badge">{{ $program->nama_bidang }}</span>
                                </td>
                                <td>
                                    {{ $program->tanggal_mulai ? \Carbon\Carbon::parse($program->tanggal_mulai)->format('d-m-Y') : '-' }}
                                </td>
                                <td>
                                    {{ $program->tanggal_selesai ? \Carbon\Carbon::parse($program->tanggal_selesai)->format('d-m-Y') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="bidang-kosong">
                                    {{ request('cari') ? 'Program kerja tidak ditemukan.' : 'Belum ada program kerja di bidang lain.' }}
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
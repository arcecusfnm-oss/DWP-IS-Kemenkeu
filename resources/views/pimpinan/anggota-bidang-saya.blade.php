@extends('layouts.bidang')

@section('title', 'Anggota Bidang')

@push('styles')
<style>
.ab-header { background: rgba(255,255,255,.85); border-radius: 18px; padding: 20px 28px; margin-bottom: 14px; box-shadow: 0 6px 14px rgba(60,30,20,.28); }
.ab-header h1 { margin: 0 0 4px; font-family: 'Gelasio', serif; font-size: 38px; font-weight: 700; color: #1a1a1a; line-height: 1.15; }
.ab-header p { margin: 0; font-size: 13px; color: #4a3a34; }

.ab-card { background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(60,30,20,.08); }
.ab-table { width: 100%; border-collapse: collapse; }
.ab-table thead th { background: #fff0ea; text-transform: uppercase; font-size: 11px; font-weight: 700; letter-spacing: .05em; color: #1a1a1a; text-align: left; padding: 14px 24px; }
.ab-table tbody td { padding: 17px 24px; font-size: 13px; color: #4a3a34; border-bottom: 1px solid #f6e6de; }
.ab-table .ab-no { text-align: center; width: 90px; }
.ab-table .ab-nama { font-weight: 700; color: #1a1a1a; }
.ab-table .ab-kosong { text-align: center; padding: 32px; }

.ab-badge { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.ab-ketua { background: #9c3f1e; color: #fff; }
.ab-wakil { background: #fbe3d8; color: #9c3f1e; }
.ab-anggota { background: #fddccd; color: #5a2a18; }

.ab-footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; padding: 16px 24px; }
.ab-info { font-size: 12px; color: #1a1a1a; }
.ab-pager { display: flex; align-items: center; gap: 12px; }
.ab-prev, .ab-num, .ab-next { display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: #1a1a1a; text-decoration: none; }
.ab-prev { background: #fbe3d8; height: 36px; padding: 0 16px; border-radius: 6px; font-weight: 500; }
.ab-num { min-width: 32px; height: 32px; border-radius: 6px; }
.ab-num.active { background: #9c3f1e; color: #fff; }
.ab-prev.disabled, .ab-next.disabled { opacity: .45; pointer-events: none; }
</style>
@endpush

@section('content')
    <div class="content bidang-page">

        <div class="ab-header">
            <h1>Anggota Bidang {{ $namaBidang }}</h1>
            <p>Daftar anggota bidang Anda.</p>
        </div>

        <div class="ab-card">
            <table class="ab-table">
                <thead>
                    <tr>
                        <th class="ab-no">No</th>
                        <th>Nama Lengkap</th>
                        <th>Nomor Telepon</th>
                        <th>Posisi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($anggota as $index => $a)
                        @php
                            $posisi = match ($a->jabatan_bidang) {
                                'ketua' => ['Ketua Bidang', 'ab-ketua'],
                                'wakil_ketua' => ['Wakil Ketua', 'ab-wakil'],
                                default => ['Anggota Bidang', 'ab-anggota'],
                            };
                        @endphp
                        <tr>
                            <td class="ab-no">{{ $anggota->firstItem() + $index }}</td>
                            <td class="ab-nama">{{ $a->nama_lengkap }}</td>
                            <td>{{ $a->nomor_telepon }}</td>
                            <td><span class="ab-badge {{ $posisi[1] }}">{{ $posisi[0] }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="ab-kosong">{{ $pesan ?? 'Tidak ada anggota yang sesuai' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($anggota->total() > 0)
                @php
                    $sekarang = $anggota->currentPage();
                    $terakhir = $anggota->lastPage();
                @endphp
                <div class="ab-footer">
                    <span class="ab-info">Menampilkan {{ $anggota->firstItem() }}–{{ $anggota->lastItem() }} dari {{ $anggota->total() }} anggota</span>

                    @if ($terakhir > 1)
                        <nav class="ab-pager" aria-label="Halaman">
                            @if ($anggota->onFirstPage())
                                <span class="ab-prev disabled">&lsaquo; Sebelumnya</span>
                            @else
                                <a class="ab-prev" href="{{ $anggota->previousPageUrl() }}">&lsaquo; Sebelumnya</a>
                            @endif

                            @for ($p = max(1, $sekarang - 2); $p <= min($terakhir, $sekarang + 2); $p++)
                                @if ($p === $sekarang)
                                    <span class="ab-num active" aria-current="page">{{ $p }}</span>
                                @else
                                    <a class="ab-num" href="{{ $anggota->url($p) }}">{{ $p }}</a>
                                @endif
                            @endfor

                            @if ($anggota->hasMorePages())
                                <a class="ab-next" href="{{ $anggota->nextPageUrl() }}">Selanjutnya &rsaquo;</a>
                            @else
                                <span class="ab-next disabled">Selanjutnya &rsaquo;</span>
                            @endif
                        </nav>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection
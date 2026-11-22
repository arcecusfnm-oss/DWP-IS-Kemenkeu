@extends(auth()->user()->peran?->nama_peran === 'Penasihat' ? 'layouts.penasihat' : 'layouts.admin')
@section('title', 'Program Kerja Seluruh Bidang')

{{--
    T-94 (B-044): daftar program kerja SELURUH bidang, hanya baca.
    Struktur dan kelas CSS disamakan dengan halaman Kelola Akun (admin/akun/index).
    Data dari ProgramKerjaController::seluruhBidang (T-93):
      $programKerja  paginator (id_program_kerja, nama_program, tujuan,
                     tanggal_mulai, tanggal_selesai, id_bidang, nama_bidang)
      $daftarBidang  untuk dropdown filter
      $bidangDipilih id_bidang terpilih atau null

    Layout 'layouts.admin' dipakai Admin dan Penasihat, jadi view ini bisa dipakai keduanya.
    Sisa ruang: kolom Status/Aksi (B-051, B-123) bisa ditambah di akhir tabel nanti
    (tambah <col>, <th class="kolom-aksi">, dan <td>). Jangan tampilkan tombol apa pun sekarang.
--}}

@section('content')
    @php
        $namaRouteIndex = auth()->user()->peran?->nama_peran === 'Penasihat'
            ? 'penasihat.program-kerja.index'
            : 'admin.program-kerja.index';

        $namaBidangDipilih = $bidangDipilih
            ? optional($daftarBidang->firstWhere('id_bidang', $bidangDipilih))->nama_bidang
            : null;
        $adaFilter = $namaBidangDipilih !== null || $statusDipilih !== null;
        $daftarStatus = ['Diajukan', 'Disetujui', 'Ditolak', 'Berjalan', 'Selesai'];

        $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $tgl = function ($nilai) use ($bulan) {
            if (! $nilai) {
                return '-';
            }
            $c = \Carbon\Carbon::parse($nilai);
            return $c->day . ' ' . $bulan[$c->month] . ' ' . $c->year;
        };

        // Warna pill per bidang: [latar, teks]. Bidang lain memakai warna bawaan akun-pill.
        $warnaBidang = [
            'Sekretariat'   => ['#f9d2bc', '#7a3b1e'],
            'Sosial Budaya' => ['#e4e7fb', '#3b4390'],
            'Ekonomi'       => ['#fdf0b5', '#6b5200'],
            'Pendidikan'    => ['#bff0b0', '#1f5a14'],
            'Bendahara'     => ['#cdf6e4', '#10634a'],
        ];
        $gayaPill = function ($nama) use ($warnaBidang) {
            return isset($warnaBidang[$nama])
                ? "background: {$warnaBidang[$nama][0]}; color: {$warnaBidang[$nama][1]};"
                : '';
        };
    @endphp

    <div class="content akun-page">

        {{-- Header --}}
        <div class="bidang-header-card">
            <div class="bidang-header">
                <div>
                    <h1>Program Kerja Seluruh Bidang</h1>
                    <p>Daftar program kerja dari semua bidang.</p>
                </div>
            </div>
        </div>

        {{-- Alert galat (filter bidang tidak valid) --}}
        @error('bidang')
            <div class="akun-alert akun-alert-galat" id="skbGalat" role="alert">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M12 8v4M12 16h.01" />
                </svg>
                <strong>{{ $message }}</strong>
                <button type="button" class="akun-alert-tutup" onclick="document.getElementById('skbGalat').remove()"
                    aria-label="Tutup">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @enderror

        {{-- Tabel + filter --}}
        <div class="akun-panel">
            <form method="GET" action="{{ route($namaRouteIndex) }}" class="akun-filter">
                <div class="akun-filter-grup">
                    <label for="fBidang">Bidang</label>
                    <select name="bidang" id="fBidang">
                        <option value="">Semua Bidang</option>
                        @foreach ($daftarBidang as $b)
                            <option value="{{ $b->id_bidang }}" @selected((int) $bidangDipilih === (int) $b->id_bidang)>
                                {{ $b->nama_bidang }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="akun-filter-grup">
                    <label for="fStatus">Status</label>
                    <select name="status" id="fStatus">
                        <option value="">Semua Status</option>
                        @foreach ($daftarStatus as $s)
                            <option value="{{ $s }}" @selected($statusDipilih === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="skb-terapkan">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polygon points="22 3 2 3 10 12.5 10 19 14 21 14 12.5 22 3" />
                    </svg>
                    Terapkan
                </button>

                <a href="{{ route($namaRouteIndex) }}" class="akun-btn-reset">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12a9 9 0 1 0 3-6.7L3 8" />
                        <path d="M3 3v5h5" />
                    </svg>
                    Atur Ulang
                </a>

                @if ($adaFilter)
                    <div class="akun-filter-info">
                        Filter Aktif:
                        @if ($namaBidangDipilih) <span>{{ $namaBidangDipilih }}</span> @endif
                        @if ($statusDipilih) <span>{{ $statusDipilih }}</span> @endif
                    </div>
                @endif
            </form>

            {{-- Tabel selalu tampil, header kolom tidak ikut hilang saat data kosong --}}
            <div class="akun-tabel-wrap">
                <table class="akun-tabel skb-tabel">
                    <colgroup>
                        <col style="width: 5%">
                        <col style="width: 22%">
                        <col style="width: 14%">
                        <col style="width: 25%">
                        <col style="width: 12%">
                        <col style="width: 12%">
                        <col style="width: 10%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Program</th>
                            <th>Bidang</th>
                            <th>Deskripsi</th>
                            <th>Tanggal Mulai</th>
                            <th>Tanggal Selesai</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($programKerja as $i => $p)
                            <tr>
                                <td>{{ $programKerja->firstItem() + $i }}</td>
                                <td><strong>{{ $p->nama_program }}</strong></td>
                                <td>
                                    <span class="akun-pill" @style([$gayaPill($p->nama_bidang) => isset($warnaBidang[$p->nama_bidang])])>{{ $p->nama_bidang }}</span>
                                </td>
                                <td>
                                    <span class="skb-deskripsi" title="{{ $p->tujuan }}">{{ $p->tujuan ?: '-' }}</span>
                                </td>
                                <td>{{ $tgl($p->tanggal_mulai) }}</td>
                                <td>{{ $tgl($p->tanggal_selesai) }}</td>
                                <td>{{ $p->status ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($programKerja->isEmpty())
                {{-- Empty state, di bawah header kolom --}}
                <div class="akun-kosong">
                    <div class="akun-kosong-ikon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                        </svg>
                    </div>
                    <h3 class="serif">Belum ada program kerja</h3>
                    <p>
                        @if ($namaBidangDipilih)
                            Tidak ada data program kerja untuk bidang <b>{{ $namaBidangDipilih }}</b>.
                            Pilih bidang lain atau atur ulang filter.
                        @elseif ($statusDipilih)
                            Tidak ada program kerja berstatus <b>{{ $statusDipilih }}</b>.
                            Pilih status lain atau atur ulang filter.
                        @else
                            Belum ada program kerja yang tercatat di bidang mana pun.
                        @endif
                    </p>
                    @if ($adaFilter)
                        <div class="akun-kosong-aksi">
                            <a href="{{ route($namaRouteIndex) }}" class="akun-btn-ghost">Atur Ulang Filter</a>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Footer / pagination --}}
            <div class="akun-footer">
                <span>Menampilkan <b>{{ $programKerja->firstItem() ?? 0 }}-{{ $programKerja->lastItem() ?? 0 }}</b> dari
                    <b>{{ $programKerja->total() }}</b> program</span>

                @if ($programKerja->hasPages())
                    <div class="akun-page-nav">
                        @if ($programKerja->onFirstPage())
                            <span class="nav mati">‹ Sebelumnya</span>
                        @else
                            <a href="{{ $programKerja->previousPageUrl() }}" class="nav">‹ Sebelumnya</a>
                        @endif

                        @foreach ($programKerja->getUrlRange(1, $programKerja->lastPage()) as $page => $url)
                            <a href="{{ $url }}"
                                class="num {{ $page == $programKerja->currentPage() ? 'aktif' : '' }}">{{ $page }}</a>
                        @endforeach

                        @if ($programKerja->hasMorePages())
                            <a href="{{ $programKerja->nextPageUrl() }}" class="nav">Selanjutnya ›</a>
                        @else
                            <span class="nav mati">Selanjutnya ›</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('styles')
        <style>
            /* Hanya untuk elemen yang tidak ada di Kelola Akun */
            .skb-terapkan {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                align-self: flex-end;
                padding: 11px 20px;
                border: 1px solid #1b4a15;
                border-radius: 10px;
                background: #1b4a15;
                color: #fff;
                font: inherit;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
            }

            /* Ukuran tombol dan chip tetap, tidak berubah saat filter aktif */
            .akun-filter .akun-btn-reset,
            .akun-filter .skb-terapkan,
            .akun-filter .akun-filter-info {
                flex: 0 0 auto;
                white-space: nowrap;
            }

            /* Lebar kolom dikunci supaya sama untuk semua isi dan filter */
            .skb-tabel {
                table-layout: fixed;
                width: 100%;
                min-width: 860px;
            }

            .skb-tabel th,
            .skb-tabel td {
                overflow-wrap: anywhere;
            }

            .skb-tabel .akun-pill {
                white-space: nowrap;
            }

            .skb-deskripsi {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
                line-height: 1.4;
            }
        </style>
    @endpush
@endsection
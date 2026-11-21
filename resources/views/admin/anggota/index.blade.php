{{--
    resources/views/admin/anggota/index.blade.php
    Tampilan daftar anggota sesuai Figma "Kelola Anggota - Daftar Anggota".
    T-43 (B-020): filter bidang/peran, pencarian, dan paginasi dibaca dari AnggotaController::index().

    Variabel yang dipakai:
      $anggota       : paginator. Tiap item: nama_lengkap, nama_panggilan, nomor_telepon,
                       nama_bidang, jabatan_bidang ('ketua'/'wakil_ketua'/null),
                       status ('aktif'/'nonaktif'), lengkap (0/1)
      $daftarPeran   : koleksi objek (id_peran, nama_peran) untuk filter
      $daftarBidang  : koleksi objek (id_bidang, nama_bidang) untuk filter
--}}
@extends('layouts.admin')

@section('title', 'Kelola Anggota')

@php
    $posisiDariPeran = [
        'Penasihat' => 'Penasihat/Wakil Penasihat',
        'Pimpinan Bidang' => 'Ketua Bidang',
        'Anggota Bidang' => 'Anggota Bidang',
    ];
    $adaPaginasi = method_exists($anggota, 'total');
    $total = $adaPaginasi ? $anggota->total() : $anggota->count();
    $dari = $adaPaginasi ? $anggota->firstItem() ?? 0 : ($total ? 1 : 0);
    $sampai = $adaPaginasi ? $anggota->lastItem() ?? 0 : $total;
    $inisial = function ($nama) {
        $kata = preg_split('/\s+/', trim((string) $nama), -1, PREG_SPLIT_NO_EMPTY);
        return mb_strtoupper(mb_substr($kata[0] ?? '', 0, 1) . mb_substr($kata[1] ?? '', 0, 1));
    };
@endphp

@push('styles')
    <style>
        .agl-aksi {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .agl-aksi a {
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            color: var(--ink);
        }

        .agl-aksi a:hover {
            background: #fdf1ea;
            color: var(--coklat-tua);
        }

        .agl-aksi a:focus-visible,
        .agl-btn-cari:focus-visible {
            outline: 2px solid #c2653d;
            outline-offset: 2px;
        }

        .agl-aksi button.agl-btn-pindah {
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: var(--ink);
            cursor: pointer;
        }

        .agl-aksi button.agl-btn-pindah:hover {
            background: #fdf1ea;
            color: var(--coklat-tua);
        }

        .agl-aksi a:focus-visible,
        .agl-aksi button:focus-visible {
            outline: 2px solid #c2653d;
            outline-offset: 2px;
        }

        .agl-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 1050;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: rgba(40, 20, 10, .5);
        }

        .agl-modal-overlay[hidden] {
            display: none;
        }

        .agl-modal {
            width: 100%;
            max-width: 440px;
            padding: 26px;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 20px 50px rgba(0, 0, 0, .25);
            text-align: left;
        }

        .agl-modal h2 {
            margin: 0 0 8px;
            font-size: 20px;
            color: var(--ink);
        }

        .agl-modal-deskripsi {
            margin: 0 0 20px;
            color: #6b625d;
            font-size: 14px;
            line-height: 1.5;
        }

        .agl-modal-field {
            margin-bottom: 20px;
        }

        .agl-modal-field label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
        }

        .agl-modal-field select {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #d9c9c0;
            border-radius: 10px;
            background: #fff;
            color: var(--ink);
            font: inherit;
        }

        .agl-modal-field select:focus-visible {
            outline: 2px solid #c2653d;
            outline-offset: 2px;
        }

        .agl-modal-aksi {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .agl-modal-batal,
        .agl-modal-simpan {
            padding: 10px 18px;
            border: 1px solid transparent;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .agl-modal-batal {
            background: #fff;
            border-color: #d9c9c0;
            color: #5b4a42;
        }

        .agl-modal-simpan {
            background: #8b451f;
            color: #fff;
        }

        .agl-modal-simpan:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .agl-belum {
            background: #ffe3c7;
            color: #9a4a14;
        }

        .agl-tabel th,
        .agl-tabel td {
            vertical-align: middle;
        }

        .agl-tabel th:last-child,
        .agl-tabel td:last-child {
            padding-right: 24px;
            padding-left: 16px;
        }

        .agl-tabel td.agl-nomor {
            text-align: center;
        }

        .agl-tabel .akun-username {
            margin-top: 1px;
        }

        .agl-posisi {
            max-width: 170px;
        }
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
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" />
                    <path d="m8 12 3 3 5-6" />
                </svg>
                <strong>{{ $pesanSukses }}</strong>
                <button type="button" class="akun-alert-tutup" id="agl-sukses-tutup" aria-label="Tutup pesan">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif

        {{-- Pesan gagal pindah bidang --}}
        @if (session('error'))
            <div class="akun-alert akun-alert-galat" role="alert" id="agl-galat">
                <strong>{{ session('error') }}</strong>
                <button type="button" class="akun-alert-tutup"
                    onclick="document.getElementById('agl-galat').remove()" aria-label="Tutup pesan">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @elseif ($errors->any())
            <div class="akun-alert akun-alert-galat" role="alert" id="agl-validasi">
                <strong>{{ $errors->first() }}</strong>
                <button type="button" class="akun-alert-tutup"
                    onclick="document.getElementById('agl-validasi').remove()" aria-label="Tutup pesan">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
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
                            <option value="{{ $p->id_peran }}" @selected((string) request('peran') === (string) $p->id_peran)>{{ $p->nama_peran }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="akun-filter-grup">
                    <label for="f-bidang">Filter Bidang</label>
                    <select id="f-bidang" name="bidang">
                        <option value="">Semua Bidang</option>
                        @foreach ($daftarBidang as $b)
                            <option value="{{ $b->id_bidang }}" @selected((string) request('bidang') === (string) $b->id_bidang)>{{ $b->nama_bidang }}</option>
                        @endforeach
                    </select>
                </div>
                <a href="{{ route('admin.anggota.index') }}" class="akun-btn-reset">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                        <path d="M3 3v5h5" />
                    </svg>
                    Atur Ulang
                </a>
                <div class="akun-cari">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" />
                        <path d="m21 21-4.3-4.3" />
                    </svg>
                    <input type="search" name="q" value="{{ request('q') }}"
                        placeholder="Cari nama atau username..." aria-label="Cari anggota">
                </div>
            </form>

            {{-- Tabel --}}
            <div class="akun-tabel-wrap">
                <table class="akun-tabel agl-tabel">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Lengkap</th>
                            <th>Nomor Telepon</th>
                            <th>Bidang</th>
                            <th>Posisi</th>
                            <th>Status</th>
                            <th>Kelengkapan Data</th>
                            <th class="kolom-aksi">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($anggota as $a)
                            @php $aktif = ($a->status ?? 'aktif') === 'aktif'; @endphp
                            <tr>
                                <td class="agl-nomor">
                                    {{ $adaPaginasi ? $anggota->firstItem() + $loop->index : $loop->iteration }}</td>
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
                                <td class="agl-posisi">
                                    {{ $posisiDariPeran[$a->nama_peran ?? ''] ?? ($a->nama_peran ?? '-') }}</td>
                                <td>
                                    <span
                                        class="akun-status {{ $aktif ? 'aktif' : 'nonaktif' }}"><i></i>{{ $aktif ? 'Aktif' : 'Nonaktif' }}</span>
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
                                        @if ($a->id_bidang !== null)
                                            <button type="button" class="agl-btn-pindah"
                                                data-url="{{ route('admin.anggota.pindah-bidang', $a->id_anggota) }}"
                                                data-nama="{{ $a->nama_lengkap }}"
                                                data-bidang-asal="{{ $a->id_bidang }}"
                                                aria-label="Pindah bidang {{ $a->nama_lengkap }}"
                                                title="Pindah bidang">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M7 7h13l-3-3" />
                                                    <path d="M20 7l-3 3" />
                                                    <path d="M17 17H4l3 3" />
                                                    <path d="M4 17l3-3" />
                                                </svg>
                                            </button>
                                        @endif

                                        <a href="{{ route('admin.anggota.edit', $a->id_anggota) }}"
                                            aria-label="Edit data {{ $a->nama_lengkap }}" title="Edit anggota">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round" aria-hidden="true">
                                                <path d="M12 20h9" />
                                                <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z" />
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="bidang-kosong">Tidak ada anggota yang sesuai.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Footer dan paginasi --}}
            <div class="akun-footer">
                <span>Menampilkan <b>{{ $dari }}–{{ $sampai }}</b> dari <b>{{ $total }}</b>
                    anggota</span>

                @if ($adaPaginasi && $anggota->hasPages())
                    <nav class="akun-page-nav" aria-label="Paginasi">
                        @if ($anggota->onFirstPage())
                            <span class="nav mati">‹ Sebelumnya</span>
                        @else
                            <a class="nav" href="{{ $anggota->previousPageUrl() }}">‹ Sebelumnya</a>
                        @endif

                        @foreach ($anggota->getUrlRange(1, $anggota->lastPage()) as $halaman => $url)
                            <a class="num {{ $halaman === $anggota->currentPage() ? 'aktif' : '' }}"
                                href="{{ $url }}"
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
    {{-- Modal pindah bidang anggota --}}
    <div class="agl-modal-overlay" id="agl-modal-overlay" hidden>
        <div class="agl-modal" role="dialog" aria-modal="true" aria-labelledby="agl-modal-title">
            <h2 id="agl-modal-title">Pindah Bidang</h2>
            <p class="agl-modal-deskripsi">
                Pilih bidang baru untuk anggota <strong id="agl-modal-nama"></strong>.
            </p>

            <form id="agl-form-pindah" method="POST">
                @csrf
                @method('PUT')

                <div class="agl-modal-field">
                    <label for="agl-bidang-tujuan">Bidang tujuan</label>
                    <select name="id_bidang" id="agl-bidang-tujuan" required>
                        <option value="">Pilih bidang tujuan</option>
                        @foreach ($daftarBidang as $b)
                            <option value="{{ $b->id_bidang }}">{{ $b->nama_bidang }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="agl-modal-aksi">
                    <button type="button" class="agl-modal-batal" id="agl-modal-batal">Batal</button>
                    <button type="submit" class="agl-modal-simpan" id="agl-modal-simpan" disabled>
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            // Tutup pesan sukses
            var tutup = document.getElementById('agl-sukses-tutup');
            var kotak = document.getElementById('agl-sukses');
            if (tutup && kotak) {
                tutup.addEventListener('click', function() {
                    kotak.hidden = true;
                });
            }

            // Dropdown filter langsung submit saat diganti
            var formFilter = document.querySelector('form.akun-filter');
            if (formFilter) {
                formFilter.querySelectorAll('select').forEach(function(s) {
                    s.addEventListener('change', function() {
                        formFilter.submit();
                    });
                });
            }

            // Modal pindah bidang
            var overlay = document.getElementById('agl-modal-overlay');
            var formPindah = document.getElementById('agl-form-pindah');
            var namaAnggota = document.getElementById('agl-modal-nama');
            var selectBidang = document.getElementById('agl-bidang-tujuan');
            var tombolSimpan = document.getElementById('agl-modal-simpan');
            var tombolBatal = document.getElementById('agl-modal-batal');
            var tombolPindah = document.querySelectorAll('.agl-btn-pindah');
            var bidangAsal = '';

            function perbaruiSimpan() {
                tombolSimpan.disabled = !selectBidang.value ||
                    selectBidang.value === bidangAsal;
            }

            function bukaModal(button) {
                formPindah.action = button.dataset.url;
                namaAnggota.textContent = button.dataset.nama;
                bidangAsal = button.dataset.bidangAsal || '';
                selectBidang.value = '';
                perbaruiSimpan();
                overlay.hidden = false;
                selectBidang.focus();
            }

            function tutupModal() {
                overlay.hidden = true;
                selectBidang.value = '';
                perbaruiSimpan();
            }

            tombolPindah.forEach(function(button) {
                button.addEventListener('click', function() {
                    bukaModal(button);
                });
            });

            selectBidang.addEventListener('change', perbaruiSimpan);
            tombolBatal.addEventListener('click', tutupModal);

            overlay.addEventListener('click', function(event) {
                if (event.target === overlay) {
                    tutupModal();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (!overlay.hidden && event.key === 'Escape') {
                    tutupModal();
                }
            });

            formPindah.addEventListener('submit', function(event) {
                if (!selectBidang.value || selectBidang.value === bidangAsal) {
                    event.preventDefault();
                    perbaruiSimpan();
                }
            });
        })();
    </script>
@endpush

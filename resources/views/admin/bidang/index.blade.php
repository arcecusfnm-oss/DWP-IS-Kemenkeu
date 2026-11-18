@extends('layouts.admin')

@section('title', 'Kelola Bidang')

@section('content')
    <div class="content bidang-page">

        {{-- Card Header --}}
        <div class="bidang-header-card">
            <div class="bidang-header">
                <div>
                    <h1>Kelola Bidang</h1>
                    <p>Kelola bidang organisasi DWP-IS Kemenkeu.</p>
                </div>

                <a href="{{ route('admin.bidang.create') }}" class="bidang-btn-tambah">
                    <span>+</span>
                    Tambah Bidang
                </a>
            </div>
        </div>

        {{-- Notifikasi Berhasil --}}
        @if (session('success'))
            <div class="akun-alert" id="bidangAlert" role="alert">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <path d="m8 12 3 3 5-6" />
                </svg>

                <strong>{{ session('success') }}</strong>

                <button type="button" class="akun-alert-tutup" onclick="document.getElementById('bidangAlert').remove()"
                    aria-label="Tutup">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif

        {{-- Card Tabel Bidang --}}
        <div class="bidang-card">
            <div class="bidang-table-wrapper">
                <table class="bidang-table">
                    <thead>
                        <tr>
                            <th style="width: 80px; text-align: center;">NO</th>
                            <th>NAMA BIDANG</th>
                            <th>KETUA</th>
                            <th style="width: 320px; text-align: center;">AKSI</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($bidangs as $index => $bidang)
                            <tr>
                                <td>{{ $index + 1 }}</td>

                                <td>{{ $bidang->nama_bidang }}</td>

                                <td>
                                    @if (isset($ketuaBidang[$bidang->id_bidang]))
                                        {{ $ketuaBidang[$bidang->id_bidang] }}
                                    @else
                                        <span class="ketua-belum">Belum ditetapkan</span>
                                    @endif
                                </td>

                                <td style="text-align: center;">
                                    <div class="bidang-aksi"
                                        style="display: inline-flex; align-items: center; justify-content: center; gap: 16px;">
                                        {{-- Tombol Tetapkan Ketua --}}
                                        <button type="button" class="ketua-btn"
                                            data-bidang-id="{{ $bidang->id_bidang }}"
                                            data-bidang-nama="{{ $bidang->nama_bidang }}"
                                            data-url="{{ route('admin.bidang.ketua.update', $bidang->id_bidang) }}"
                                            style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; font-size: 13px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <circle cx="9" cy="7" r="4" />
                                                <path d="M3 21v-2a4 4 0 0 1 4-4h4" />
                                                <path d="m16 19 2 2 4-4" />
                                            </svg>
                                            Tetapkan Ketua
                                        </button>

                                        {{-- Tombol Edit --}}
                                        <a href="{{ route('admin.bidang.edit', $bidang->id_bidang) }}"
                                            aria-label="Edit bidang"
                                            style="display: inline-flex; align-items: center; gap: 6px; color: var(--coklat-tua); text-decoration: none; font-weight: 600; font-size: 13px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path d="M12 20h9" />
                                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" />
                                            </svg>
                                            Edit
                                        </a>

                                        {{-- Tombol Hapus --}}
                                        <form method="POST"
                                            action="{{ route('admin.bidang.destroy', $bidang->id_bidang) }}"
                                            onsubmit="return confirm('Yakin ingin menghapus bidang ini?')"
                                            style="margin: 0; display: inline-block;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" aria-label="Hapus bidang"
                                                style="display: inline-flex; align-items: center; gap: 6px; background: transparent; border: none; cursor: pointer; color: #b94b3c; font-weight: 600; font-size: 13px; padding: 0;">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6" />
                                                </svg>

                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="bidang-kosong">
                                    Belum ada data bidang.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- Dialog tetapkan ketua --}}
    <div class="ketua-overlay" id="ketuaOverlay" hidden>
        <div class="ketua-dialog" role="dialog" aria-modal="true" aria-labelledby="ketuaJudul">
            <h3 id="ketuaJudul">Tetapkan Ketua Bidang</h3>
            <p>Bidang <b id="ketuaBidangNama"></b></p>

            @if ($errors->has('id_anggota'))
                <div class="pesan pesan-error" role="alert">{{ $errors->first('id_anggota') }}</div>
            @endif

            <form method="POST" id="ketuaForm" action="">
                @csrf
                @method('PUT')
                <input type="hidden" name="bidang_asal" id="ketuaBidangAsal" value="">

                <label for="ketuaSelect" class="ketua-label">Anggota <span>*</span></label>
                <select id="ketuaSelect" name="id_anggota" class="ketua-select"></select>

                <p class="ketua-kosong" id="ketuaKosong" hidden>
                    Belum ada anggota aktif di bidang ini. Tambahkan dulu lewat Kelola Anggota.
                </p>

                <div class="ketua-aksi">
                    <button type="button" class="ketua-batal" id="ketuaBatal">Batal</button>
                    <button type="submit" class="ketua-simpan" id="ketuaSimpan">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    <div id="ketuaData" hidden data-anggota='@json($anggotaBidang)' data-buka='@json(old('bidang_asal'))'
        data-lama='@json(old('id_anggota'))'></div>
@endsection

@push('styles')
    <style>
        .ketua-belum {
            color: #927d74;
            font-style: italic;
        }

        .bidang-aksi .ketua-btn {
            color: var(--coklat-tua);
        }

        .ketua-overlay {
            position: fixed;
            inset: 0;
            background: rgba(40, 20, 10, .5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 16px;
        }

        .ketua-overlay[hidden] {
            display: none;
        }

        .ketua-dialog {
            background: #fff;
            border-radius: 16px;
            padding: 28px;
            max-width: 440px;
            width: 100%;
            box-shadow: 0 20px 50px rgba(0, 0, 0, .25);
        }

        .ketua-dialog h3 {
            margin: 0 0 6px;
            font-size: 20px;
            text-align: center;
            color: var(--coklat-tua);
        }

        .ketua-dialog>p {
            margin: 0 0 18px;
            text-align: center;
            color: #5b4a42;
            font-size: 14px;
        }

        .ketua-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
        }

        .ketua-label span {
            color: #d4351c;
        }

        .ketua-select {
            width: 100%;
            height: 46px;
            padding: 0 14px;
            font: 400 14px "Inter", system-ui, sans-serif;
            color: var(--ink);
            background: #fff;
            border: 1px solid #e9d8d0;
            border-radius: 8px;
            outline: none;
        }

        .ketua-select:focus {
            border-color: #c2653d;
            box-shadow: 0 0 0 3px rgba(194, 101, 61, .16);
        }

        .ketua-kosong {
            margin: 12px 0 0;
            font-size: 13px;
            color: #927d74;
        }

        .ketua-aksi {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 24px;
        }

        .ketua-batal,
        .ketua-simpan {
            padding: 10px 22px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .ketua-batal {
            background: #fff;
            border-color: #d9c9c0;
            color: #5b4a42;
        }

        .ketua-simpan {
            background: var(--coklat-tua);
            color: #fff;
        }

        .ketua-simpan:disabled {
            opacity: .4;
            cursor: not-allowed;
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function() {
            const d = document.getElementById('ketuaData').dataset;
            const dataAnggota = JSON.parse(d.anggota);
            const bukaId = JSON.parse(d.buka);
            const lamaId = JSON.parse(d.lama);

            const overlay = document.getElementById('ketuaOverlay');
            const form = document.getElementById('ketuaForm');
            const select = document.getElementById('ketuaSelect');
            const nama = document.getElementById('ketuaBidangNama');
            const asal = document.getElementById('ketuaBidangAsal');
            const kosong = document.getElementById('ketuaKosong');
            const simpan = document.getElementById('ketuaSimpan');
            if (!overlay) return;

            function bukaModal(btn, pilih) {
                const id = btn.dataset.bidangId;
                const daftar = dataAnggota[id] || [];

                form.action = btn.dataset.url;
                nama.textContent = btn.dataset.bidangNama;
                asal.value = id;

                select.replaceChildren();
                const awal = document.createElement('option');
                awal.value = '';
                awal.textContent = 'Pilih anggota';
                select.appendChild(awal);

                daftar.forEach(function(a) {
                    const o = document.createElement('option');
                    o.value = a.id;
                    o.textContent = a.ketua ? a.nama + ' (ketua saat ini)' : a.nama;
                    if (pilih ? String(pilih) === String(a.id) : a.ketua) o.selected = true;
                    select.appendChild(o);
                });

                const adaAnggota = daftar.length > 0;
                kosong.hidden = adaAnggota;
                select.disabled = !adaAnggota;
                simpan.disabled = !adaAnggota;

                overlay.hidden = false;
            }

            function tutup() {
                overlay.hidden = true;
            }

            document.querySelectorAll('.ketua-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    bukaModal(btn, null);
                });
            });

            document.getElementById('ketuaBatal').addEventListener('click', tutup);
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) tutup();
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') tutup();
            });

            // Kalau server menolak, buka lagi modal bidang yang tadi
            if (bukaId) {
                const btn = document.querySelector('.ketua-btn[data-bidang-id="' + bukaId + '"]');
                if (btn) bukaModal(btn, lamaId);
            }
        })();
    </script>
@endpush
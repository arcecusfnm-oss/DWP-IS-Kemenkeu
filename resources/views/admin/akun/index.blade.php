@extends('layouts.admin')

@section('content')
    @php
        $fPeran = request('peran');
        $fBidang = request('bidang');
        $fCari = trim(request('q', ''));

        $jumlahFilter = collect([$fPeran, $fBidang, $fCari])
            ->filter()
            ->count();
        $adaFilter = $jumlahFilter > 0;
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

        {{-- Alert sukses --}}
        @if (session('sukses'))
            <div class="akun-alert" id="akunAlert">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <path d="m8 12 3 3 5-6" />
                </svg>
                <strong>{{ session('sukses', 'Data berhasil disimpan.') }}</strong>
                <button type="button" class="akun-alert-tutup" onclick="document.getElementById('akunAlert').remove()"
                    aria-label="Tutup">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif

        {{-- Alert galat --}}
        @if (session('galat'))
            <div class="akun-alert akun-alert-galat" id="akunGalat" role="alert">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M12 8v4M12 16h.01" />
                </svg>
                <strong>{{ session('galat') }}</strong>
                <button type="button" class="akun-alert-tutup" onclick="document.getElementById('akunGalat').remove()"
                    aria-label="Tutup">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif

        {{-- Kartu statistik --}}
        <div class="akun-stats">
            <div class="akun-stat">
                <div class="akun-stat-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </div>
                <div><small>TOTAL AKUN</small><b class="serif">{{ number_format($stat['total'], 0, ',', '.') }} Akun</b>
                </div>
            </div>
            <div class="akun-stat">
                <div class="akun-stat-icon hijau">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        <path d="m9 12 2 2 4-4" />
                    </svg>
                </div>
                <div><small>AKUN AKTIF</small><b class="serif">{{ number_format($stat['aktif'], 0, ',', '.') }} Akun</b>
                </div>
            </div>
            <div class="akun-stat">
                <div class="akun-stat-icon merah">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="m17 8 5 5M22 8l-5 5" />
                    </svg>
                </div>
                <div><small>NONAKTIF</small><b class="serif">{{ number_format($stat['nonaktif'], 0, ',', '.') }} Akun</b>
                </div>
            </div>
            <div class="akun-stat">
                <div class="akun-stat-icon biru">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        <path d="M12 8v4M12 16h.01" />
                    </svg>
                </div>
                <div><small>HAK ADMIN</small><b class="serif">{{ number_format($stat['admin'], 0, ',', '.') }} Petugas</b>
                </div>
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
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12a9 9 0 1 0 3-6.7L3 8" />
                        <path d="M3 3v5h5" />
                    </svg>
                    Atur Ulang
                </a>

                @if ($adaFilter)
                    <div class="akun-filter-info">
                        Filter Aktif: <span>{{ $jumlahFilter }} Kriteria</span>
                    </div>
                @else
                    <div class="akun-cari">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round">
                            <circle cx="11" cy="11" r="7" />
                            <path d="m21 21-4.3-4.3" />
                        </svg>
                        <input type="text" name="q" value="{{ request('q') }}"
                            placeholder="Cari nama atau username...">
                    </div>
                @endif
            </form>

            @if ($akun->isEmpty())
                {{-- Empty state --}}
                <div class="akun-kosong">
                    <div class="akun-kosong-ikon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h7M3 12h5M3 18h7" />
                            <circle cx="16" cy="14" r="4" />
                            <path d="m21 19-2.5-2.5" />
                        </svg>
                    </div>
                    <h3 class="serif">Tidak ada akun yang sesuai dengan filter</h3>
                    <p>
                        Tidak ditemukan data pengguna
                        @if ($fPeran)
                            untuk peran <b>{{ $fPeran }}</b>
                        @endif
                        @if ($fBidang)
                            pada bidang <b>{{ $fBidang }}</b>
                        @endif
                        @if ($fCari)
                            dengan kata kunci <b>{{ request('q') }}</b>
                        @endif.
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
                                <th class="kolom-aksi">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($akun as $i => $a)
                                @php
                                    $inisial = collect(explode(' ', $a->nama_lengkap))
                                        ->take(2)
                                        ->map(fn($k) => strtoupper(substr($k, 0, 1)))
                                        ->implode('');
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
                                    <td>{{ $a->nama_bidang ?? '—' }}</td>
                                    <td>
                                        <span class="akun-status {{ $a->status === 'aktif' ? 'aktif' : 'nonaktif' }}">
                                            <i></i>{{ ucfirst($a->status) }}
                                        </span>
                                    </td>
                                    <td>

                                        <div class="akun-aksi">
                                            {{-- Slot 1: Edit --}}
                                            <span class="akun-aksi-slot">
                                                <a href="{{ route('admin.akun.edit', $a->id_pengguna) }}" title="Edit">
                                                    <svg width="18" height="18" viewBox="0 0 24 24"
                                                        fill="none" stroke="currentColor" stroke-width="2"
                                                        stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M12 20h9" />
                                                        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" />
                                                    </svg>
                                                </a>
                                            </span>

                                            {{-- Slot 2: Ubah peran --}}
                                            <span class="akun-aksi-slot">
                                                @if (auth()->id() == $a->id_pengguna)
                                                    <button type="button" id="bukaAlih" title="Alih peran Admin">
                                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M17 3l4 4-4 4" /><path d="M3 7h18" />
                                                            <path d="M7 21l-4-4 4-4" /><path d="M21 17H3" />
                                                        </svg>
                                                    </button>
                                                @else
                                                    <a href="{{ route('admin.akun.peran.edit', $a->id_pengguna) }}"
                                                        title="Ubah peran">
                                                        <svg width="18" height="18" viewBox="0 0 24 24"
                                                            fill="none" stroke="currentColor" stroke-width="2"
                                                            stroke-linecap="round" stroke-linejoin="round">
                                                            <circle cx="9" cy="7" r="4" />
                                                            <path d="M3 21v-2a4 4 0 0 1 4-4h4" />
                                                            <circle cx="18" cy="18" r="2" />
                                                            <path
                                                                d="M18 14v1M18 21v1M21.5 16l-.9.5M15.4 19.5l-.9.5M21.5 20l-.9-.5M15.4 16.5l-.9-.5" />
                                                        </svg>
                                                    </a>
                                                @endif
                                            </span>

                                            {{-- Slot 3: Reset password --}}
                                            <span class="akun-aksi-slot">
                                                @if (auth()->id() == $a->id_pengguna)
                                                    <button type="button" class="hapus"
                                                        title="Tidak bisa mereset kata sandi akun sendiri" disabled>
                                                        <svg width="18" height="18" viewBox="0 0 24 24"
                                                            fill="none" stroke="currentColor" stroke-width="2"
                                                            stroke-linecap="round" stroke-linejoin="round">
                                                            <circle cx="7.5" cy="15.5" r="4.5" />
                                                            <path d="m10.7 12.3 9.3-9.3M17 6l3 3M14 9l2 2" />
                                                        </svg>
                                                    </button>
                                                @else
                                                    <button type="button" class="reset-password" title="Reset kata sandi"
                                                        data-reset-url="{{ route('admin.akun.reset-password', $a->id_pengguna) }}"
                                                        data-reset-nama="{{ $a->nama_lengkap }}">
                                                        <svg width="18" height="18" viewBox="0 0 24 24"
                                                            fill="none" stroke="currentColor" stroke-width="2"
                                                            stroke-linecap="round" stroke-linejoin="round">
                                                            <circle cx="7.5" cy="15.5" r="4.5" />
                                                            <path d="m10.7 12.3 9.3-9.3M17 6l3 3M14 9l2 2" />
                                                        </svg>
                                                    </button>
                                                @endif
                                            </span>

                                            {{-- Slot 4: Nonaktifkan --}}
                                            <span class="akun-aksi-slot">
                                                @if ($a->status === 'aktif' && auth()->id() != $a->id_pengguna)
                                                    <button type="button" class="nonaktifkan-akun"
                                                        title="Nonaktifkan akun"
                                                        data-nonaktifkan-url="{{ route('admin.akun.nonaktifkan', $a->id_pengguna) }}"
                                                        data-nonaktifkan-nama="{{ $a->nama_lengkap }}">
                                                        <svg width="18" height="18" viewBox="0 0 24 24"
                                                            fill="none" stroke="currentColor" stroke-width="2"
                                                            stroke-linecap="round" stroke-linejoin="round">
                                                            <circle cx="12" cy="12" r="10" />
                                                            <path d="M4.93 4.93l14.14 14.14" />
                                                        </svg>
                                                    </button>
                                                @endif
                                            </span>

                                            {{-- Slot 5: Hapus --}}
                                            <span class="akun-aksi-slot">
                                                <button type="button"
                                                    title="{{ auth()->id() == $a->id_pengguna ? 'Tidak bisa menghapus akun sendiri' : 'Hapus' }}"
                                                    class="hapus"
                                                    data-hapus-url="{{ route('admin.akun.destroy', $a->id_pengguna) }}"
                                                    data-hapus-nama="{{ $a->nama_lengkap }}" @disabled(auth()->id() == $a->id_pengguna)>
                                                    <svg width="18" height="18" viewBox="0 0 24 24"
                                                        fill="none" stroke="currentColor" stroke-width="2"
                                                        stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6" />
                                                    </svg>
                                                </button>
                                            </span>
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
                <span>Menampilkan <b>{{ $akun->firstItem() ?? 0 }}-{{ $akun->lastItem() ?? 0 }}</b> dari
                    <b>{{ $akun->total() }}</b> akun</span>

                @if ($akun->hasPages())
                    <div class="akun-page-nav">
                        @if ($akun->onFirstPage())
                            <span class="nav mati">‹ Sebelumnya</span>
                        @else
                            <a href="{{ $akun->previousPageUrl() }}" class="nav">‹ Sebelumnya</a>
                        @endif

                        @foreach ($akun->getUrlRange(1, $akun->lastPage()) as $page => $url)
                            <a href="{{ $url }}"
                                class="num {{ $page == $akun->currentPage() ? 'aktif' : '' }}">{{ $page }}</a>
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

    {{-- Dialog alih admin --}}
    <div class="hapus-overlay" id="alihOverlay" hidden>
        <div class="hapus-dialog" style="text-align:left" role="dialog" aria-modal="true" aria-labelledby="alihJudul">
            <h3 id="alihJudul" style="text-align:center">Alih Peran Admin</h3>
            <p style="text-align:center">
                Akun tujuan akan menjadi Admin, dan akun Anda turun ke peran yang dipilih.
                Setelah ini Anda tidak bisa membuka halaman Admin lagi.
            </p>

            @if ($errors->any())
                <div class="akun-alert-error" style="margin-bottom:12px">
                    <div><strong>{{ $errors->first() }}</strong></div>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.akun.alih-admin') }}">
                @csrf

                <div class="akun-field">
                    <label for="alihTujuan">Akun tujuan <em>*</em></label>
                    <div class="akun-input">
                        <select id="alihTujuan" name="id_tujuan">
                            <option value="">Pilih akun tujuan</option>
                            @foreach ($tujuanAlih as $t)
                                <option value="{{ $t->id_pengguna }}" @selected((string) old('id_tujuan') === (string) $t->id_pengguna)>
                                    {{ $t->nama_lengkap }} ({{ $t->nama_peran }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="akun-field">
                    <label for="alihPeran">Peran baru untuk akun Anda <em>*</em></label>
                    <div class="akun-input">
                        <select id="alihPeran" name="peran_turun">
                            <option value="">Pilih peran</option>
                            @foreach ($peranTurun as $p)
                                <option value="{{ $p }}" @selected(old('peran_turun') === $p)>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="akun-field">
                    <label>Bidang <em id="alihBintang">*</em></label>
                    <div id="alihDaftarBidang"></div>
                    <button type="button" id="alihTambah" class="akun-btn-batal" style="margin-top:8px" hidden>+ Tambah bidang</button>
                </div>

                <div class="hapus-aksi">
                    <button type="button" class="hapus-batal" id="alihBatal">Batal</button>
                    <button type="submit" class="hapus-ya">Ya, Alihkan</button>
                </div>
            </form>
        </div>
    </div>

    <div id="alihData" hidden
        data-peta='@json($petaBidang)'
        data-penuh='@json($penuh)'
        data-tanpa='@json($tanpaBidang)'
        data-multi='@json($peranMulti)'
        data-lama='@json(array_values((array) old("bidang", [])))'
        data-buka='{{ old('id_tujuan') !== null || old('peran_turun') !== null ? 1 : 0 }}'>
    </div>

    <script>
    (function () {
        const overlay = document.getElementById('alihOverlay');
        const tombol  = document.getElementById('bukaAlih');
        if (!overlay) return;

        const d     = document.getElementById('alihData').dataset;
        const peta  = JSON.parse(d.peta);
        const penuh = JSON.parse(d.penuh);
        const tanpa = JSON.parse(d.tanpa);
        const multi = JSON.parse(d.multi);
        const lama  = JSON.parse(d.lama);

        const peran   = document.getElementById('alihPeran');
        const daftar  = document.getElementById('alihDaftarBidang');
        const tambah  = document.getElementById('alihTambah');
        const bintang = document.getElementById('alihBintang');

        function baris(boleh, terisi, terpilih, bisaHapus) {
            const wrap = document.createElement('div');
            wrap.className = 'akun-input';
            wrap.style.marginBottom = '8px';

            const sel = document.createElement('select');
            sel.name = 'bidang[]';
            sel.innerHTML = '<option value="">Pilih Bidang</option>';
            boleh.forEach(function (b) {
                const o = document.createElement('option');
                const isi = terisi.includes(b);
                o.value = b;
                o.textContent = isi ? b + ' (sudah terisi)' : b;
                o.disabled = isi;
                o.selected = (b === terpilih);
                sel.appendChild(o);
            });
            wrap.appendChild(sel);

            if (bisaHapus) {
                const x = document.createElement('button');
                x.type = 'button';
                x.textContent = '✕';
                x.setAttribute('aria-label', 'Hapus bidang');
                x.style.cssText = 'position:absolute; top:0; right:0; width:46px; height:46px; color:#b42318;';
                x.onclick = function () { if (daftar.children.length > 1) wrap.remove(); };
                wrap.appendChild(x);
            }
            return wrap;
        }

        function render(pakaiLama) {
            daftar.innerHTML = '';
            const p = peran.value;

            if (!p) {
                daftar.innerHTML = '<div class="akun-input"><select disabled><option>Pilih peran dulu</option></select></div>';
                tambah.hidden = true;
                return;
            }
            if (tanpa.includes(p)) {
                daftar.innerHTML = '<div class="akun-input"><input type="text" value="Tidak memerlukan bidang" disabled></div>';
                tambah.hidden = true;
                bintang.hidden = true;
                return;
            }
            bintang.hidden = false;

            const boleh   = peta[p] || [];
            const terisi  = penuh[p] || [];
            const isMulti = multi.includes(p);
            tambah.hidden = !isMulti;

            let awal = pakaiLama ? lama.filter(b => boleh.includes(b)) : [];
            if (!isMulti) awal = awal.slice(0, 1);
            if (!awal.length) awal = [boleh.length === 1 ? boleh[0] : ''];

            awal.forEach(function (b, i) {
                daftar.appendChild(baris(boleh, terisi, b, isMulti && i > 0));
            });
        }

        tambah.addEventListener('click', function () {
            const p = peran.value;
            daftar.appendChild(baris(peta[p] || [], penuh[p] || [], '', true));
        });
        peran.addEventListener('change', function () { render(false); });

        function tutup() { overlay.hidden = true; }
        if (tombol) tombol.addEventListener('click', function () { overlay.hidden = false; });
        document.getElementById('alihBatal').addEventListener('click', tutup);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) tutup(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutup(); });

        render(true);
        if (d.buka === '1') overlay.hidden = false;
    })();
    </script>

    {{-- Dialog konfirmasi nonaktifkan akun --}}
    <div class="hapus-overlay" id="nonaktifkanOverlay" hidden>
        <div class="hapus-dialog" role="dialog" aria-modal="true" aria-labelledby="nonaktifkanJudul">

            <div class="hapus-ikon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M4.93 4.93l14.14 14.14" />
                </svg>
            </div>

            <h3 id="nonaktifkanJudul">Nonaktifkan Akun?</h3>

            <p>
                Akun <b id="nonaktifkanNama"></b> akan dinonaktifkan.
                Pengguna tidak dapat masuk kembali dan seluruh sesi aktifnya akan dicabut.
            </p>

            <form method="POST" id="nonaktifkanForm" action="">
                @csrf
                <div class="hapus-aksi">
                    <button type="button" class="hapus-batal" id="nonaktifkanBatal">
                        Batal
                    </button>
                    <button type="submit" class="hapus-ya">
                        Ya, Nonaktifkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Dialog konfirmasi hapus akun --}}
    <div class="hapus-overlay" id="hapusOverlay" hidden>
        <div class="hapus-dialog" role="dialog" aria-modal="true" aria-labelledby="hapusJudul">
            <div class="hapus-ikon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6" />
                </svg>
            </div>
            <h3 id="hapusJudul">Hapus Akun Pengguna?</h3>
            <p>
                Akun <b id="hapusNama"></b> akan dihapus dari sistem.
                Pengguna ini tidak akan dapat masuk ke sistem lagi.
            </p>
            <form method="POST" id="hapusForm" action="">
                @csrf
                @method('DELETE')
                <div class="hapus-aksi">
                    <button type="button" class="hapus-batal" id="hapusBatal">
                        Batal
                    </button>
                    <button type="submit" class="hapus-ya">
                        Ya, Hapus Akun
                    </button>
                </div>
            </form>
        </div>
    </div>


    {{-- Dialog konfirmasi reset kata sandi --}}
    <div class="hapus-overlay" id="resetOverlay" hidden>
        <div class="hapus-dialog" role="dialog" aria-modal="true" aria-labelledby="resetJudul">

            <div class="hapus-ikon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="7.5" cy="15.5" r="4.5" />
                    <path d="m10.7 12.3 9.3-9.3M17 6l3 3M14 9l2 2" />
                </svg>
            </div>

            <h3 id="resetJudul">Reset Kata Sandi?</h3>

            <p>
                Kata sandi akun <b id="resetNama"></b> akan direset.
                Sistem akan membuat kata sandi sementara yang baru.
            </p>

            <form method="POST" id="resetForm" action="">
                @csrf
                <div class="hapus-aksi">
                    <button type="button" class="hapus-batal" id="resetBatal">
                        Batal
                    </button>
                    <button type="submit" class="hapus-ya">
                        Ya, Reset Kata Sandi
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal kata sandi sementara --}}
    @if (session()->has('kata_sandi_sementara'))
        <div class="password-overlay" id="passwordOverlay">
            <div class="password-dialog" role="dialog" aria-modal="true" aria-labelledby="passwordJudul">

                <div class="password-berhasil">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m5 12 4 4L19 6" />
                    </svg>
                </div>

                <span class="password-label">RESET BERHASIL</span>

                <h3 id="passwordJudul">Kata Sandi Sementara</h3>

                <p>
                    Kata sandi akun berhasil direset. Salin dan berikan
                    kata sandi sementara ini kepada pengguna terkait.
                </p>

                <div class="password-info">
                    <span>KATA SANDI SEMENTARA</span>

                    <div class="password-value">
                        <code id="passwordSementara">{{ session('kata_sandi_sementara') }}</code>

                        <button type="button" id="salinPassword" title="Salin kata sandi">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="8" y="8" width="13" height="13" rx="2" />
                                <path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="password-peringatan">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round">
                        <circle cx="12" cy="12" r="10" />
                        <path d="M12 8v4M12 16h.01" />
                    </svg>

                    <span>
                        Kata sandi ini hanya ditampilkan satu kali.
                        Pastikan sudah disalin sebelum menutup jendela ini.
                    </span>
                </div>

                <button type="button" class="password-tutup" id="tutupPassword">
                    Saya Mengerti
                </button>

                <span class="password-copy-status" id="copyStatus" aria-live="polite"></span>
            </div>
        </div>
    @endif

    @push('styles')
        <style>
            .hapus-overlay {
                position: fixed;
                inset: 0;
                background: rgba(40, 20, 10, .5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                padding: 16px;
            }

            .hapus-overlay[hidden] {
                display: none;
            }

            .hapus-dialog {
                background: #fff;
                border-radius: 16px;
                padding: 28px;
                max-width: 420px;
                width: 100%;
                text-align: center;
                box-shadow: 0 20px 50px rgba(0, 0, 0, .25);
            }

            .hapus-ikon {
                width: 56px;
                height: 56px;
                margin: 0 auto 14px;
                border-radius: 50%;
                background: #fde8e6;
                color: #c0392b;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .hapus-dialog h3 {
                margin: 0 0 8px;
                font-size: 20px;
            }

            .hapus-dialog p {
                margin: 0 0 22px;
                color: #5b4a42;
                font-size: 14px;
                line-height: 1.5;
            }

            .hapus-aksi {
                display: flex;
                gap: 10px;
                justify-content: center;
            }

            .hapus-batal,
            .hapus-ya {
                padding: 10px 22px;
                border-radius: 10px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                border: 1px solid transparent;
            }

            .hapus-batal {
                background: #fff;
                border-color: #d9c9c0;
                color: #5b4a42;
            }

            .hapus-ya {
                background: #c0392b;
                color: #fff;
            }

            .akun-aksi button.hapus:disabled {
                opacity: .35;
                cursor: not-allowed;
            }
        </style>
    @endpush

    <script>
        (function() {
            const overlay = document.getElementById('nonaktifkanOverlay');
            const form = document.getElementById('nonaktifkanForm');
            const nama = document.getElementById('nonaktifkanNama');
            const batal = document.getElementById('nonaktifkanBatal');

            if (!overlay || !form || !nama || !batal) return;

            function tutup() {
                overlay.hidden = true;
            }

            document.querySelectorAll('.nonaktifkan-akun').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    form.action = btn.dataset.nonaktifkanUrl;
                    nama.textContent = btn.dataset.nonaktifkanNama;
                    overlay.hidden = false;
                });
            });

            batal.addEventListener('click', tutup);

            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) tutup();
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') tutup();
            });
        })();
    </script>

    <script>
        (function() {
            const overlay = document.getElementById('hapusOverlay');
            const form = document.getElementById('hapusForm');
            const nama = document.getElementById('hapusNama');
            if (!overlay) return;

            function tutup() {
                overlay.hidden = true;
            }

            document.querySelectorAll('button.hapus[data-hapus-url]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (btn.disabled) return;
                    form.action = btn.dataset.hapusUrl;
                    nama.textContent = btn.dataset.hapusNama;
                    overlay.hidden = false;
                });
            });

            document.getElementById('hapusBatal').addEventListener('click', tutup);
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) tutup();
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') tutup();
            });
        })();
    </script>

    <script>
        (function() {
            // ================================
            // DIALOG KONFIRMASI RESET
            // ================================

            const overlay = document.getElementById('resetOverlay');
            const form = document.getElementById('resetForm');
            const nama = document.getElementById('resetNama');
            const batal = document.getElementById('resetBatal');

            if (overlay && form) {
                function tutupReset() {
                    overlay.hidden = true;
                }

                document.querySelectorAll('.reset-password').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        form.action = btn.dataset.resetUrl;
                        nama.textContent = btn.dataset.resetNama;
                        overlay.hidden = false;
                    });
                });

                batal.addEventListener('click', tutupReset);

                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) tutupReset();
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') tutupReset();
                });
            }

            // ================================
            // MODAL PASSWORD SEMENTARA
            // ================================

            const passwordOverlay = document.getElementById('passwordOverlay');
            const salin = document.getElementById('salinPassword');
            const password = document.getElementById('passwordSementara');
            const tutup = document.getElementById('tutupPassword');
            const status = document.getElementById('copyStatus');

            if (passwordOverlay) {
                tutup.addEventListener('click', function() {
                    passwordOverlay.remove();
                });

                passwordOverlay.addEventListener('click', function(e) {
                    if (e.target === passwordOverlay) {
                        passwordOverlay.remove();
                    }
                });

                salin.addEventListener('click', async function() {
                    try {
                        await navigator.clipboard.writeText(password.textContent.trim());
                        status.textContent = 'Kata sandi berhasil disalin!';
                        salin.innerHTML = '✓';
                    } catch (error) {
                        status.textContent = 'Gagal menyalin. Silakan salin secara manual.';
                    }
                });
            }
        })();
    </script>
@endsection

@extends('layouts.admin')

@section('title', 'Tambah Anggota')

@php
    $tingkatPendidikan = ['SMA/SMK', 'D3', 'D4', 'S1 (Sarjana)', 'S2 (Magister)', 'S3 (Doktoral)'];
    $kolomTambahan = ['nama_panggilan', 'tempat_lahir', 'tanggal_lahir', 'email', 'alamat_lengkap',
        'nama_suami', 'id_unit_kerja', 'tingkat_pendidikan', 'bidang_pendidikan', 'keahlian',
        'bidang_usaha', 'instansi_kerja'];
    $tambahanBerisiError = collect($kolomTambahan)->contains(fn ($k) => $errors->has($k));
    $ikonSalah = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>';
@endphp

@push('styles')
<style>
    /* Hanya untuk elemen yang belum ada di admin.css (kolom baca-saja, textarea, data tambahan) */
    .ang-lebar { width: auto; max-width: none; margin: 16px 16px 0; }
    @media (max-width: 768px) { .ang-lebar { margin: 16px 0 0; } }
    .akun-input.polos input,
    .akun-input.polos select { padding-left: 14px; }
    .akun-input input[readonly] { background: #f7f1ed; color: var(--coklat); cursor: not-allowed; }
    .akun-input textarea { width: 100%; min-height: 90px; padding: 12px 14px; font: 400 14px "Inter", system-ui, sans-serif; color: var(--ink); background: #fff; border: 1px solid #e9d8d0; border-radius: 8px; outline: none; resize: vertical; }
    .akun-input textarea:focus { border-color: #c2653d; box-shadow: 0 0 0 3px rgba(194, 101, 61, .16); }
    .akun-field.has-error .akun-input textarea { background: #fde2d6; border-color: #f4b99a; }
    .ang-more { margin: 8px 0 4px; border: 1px solid #f0ded4; border-radius: 10px; background: #fffaf6; }
    .ang-more > summary { cursor: pointer; padding: 14px 18px; font-size: 14px; font-weight: 600; color: #000; list-style: none; }
    .ang-more > summary::-webkit-details-marker { display: none; }
    .ang-more > summary::after { content: "\25BE"; float: right; transition: transform .15s; }
    .ang-more[open] > summary::after { transform: rotate(180deg); }
    .ang-more-body { padding: 4px 20px 8px; }
    .ang-more h3 { margin: 18px 0 12px; font-size: 14px; font-weight: 600; color: #9a3f1c; }
</style>
@endpush

@section('content')
<div class="content akun-page">
    <div class="akun-form-card">

        <div class="akun-form-head">
            <div>
                <h1>Tambah Anggota</h1>
                <p>Pilih akun, lalu lengkapi data anggota.</p>
            </div>
            <span class="akun-chip">Formulir Anggota</span>
        </div>

        @if (session('error'))
            <div class="akun-alert-error ang-lebar" role="alert">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <div>
                    <strong>Data belum bisa disimpan</strong>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        @elseif ($errors->any())
            <div class="akun-alert-error ang-lebar" role="alert" id="ang-summary">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <div>
                    <strong>Terdapat {{ $errors->count() }} kesalahan pengisian formulir</strong>
                    <span>Data belum bisa disimpan. Periksa kolom yang ditandai merah.</span>
                </div>
            </div>
        @else
            <div class="akun-info-box ang-lebar" role="note">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                Kolom bertanda bintang (*) wajib diisi. Nama lengkap dan bidang terisi otomatis dari akun yang dipilih. Data tambahan boleh dikosongkan dan dapat dilengkapi anggota sendiri.
            </div>
        @endif

        <form id="form-anggota" action="{{ route('admin.anggota.store') }}" method="POST" class="akun-form" novalidate>
        @csrf

            <div class="akun-form-judul">
                <h2><i></i> Informasi Anggota</h2>
                <span>✳ Wajib diisi</span>
            </div>

            {{-- Akun terkait --}}
            <div class="akun-field {{ $errors->has('id_pengguna') ? 'has-error' : '' }}">
                <label for="id_pengguna">Akun Terkait <em>*</em></label>
                <div class="akun-input">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/></svg>
                    <select id="id_pengguna" name="id_pengguna" aria-describedby="err-id_pengguna" required>
                        <option value="">Pilih Akun</option>
                        @foreach ($akunTersedia as $akun)
                            <option value="{{ $akun->id_pengguna }}"
                                    data-nama="{{ $akun->nama }}"
                                    data-peran="{{ $akun->peran }}"
                                    data-id-bidang="{{ $akun->id_bidang }}"
                                    data-bidang="{{ $akun->nama_bidang }}"
                                    @selected(old('id_pengguna') == $akun->id_pengguna)>
                                {{ $akun->username }} - {{ $akun->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <p class="akun-error" id="err-id_pengguna" role="alert" @if (! $errors->has('id_pengguna')) hidden @endif>{!! $ikonSalah !!} <span>{{ $errors->first('id_pengguna') }}</span></p>
                @if ($akunTersedia->isEmpty())
                    <p class="akun-hint">Semua akun sudah terhubung dengan anggota. Buat akun baru di menu Kelola Akun.</p>
                @endif
            </div>

            {{-- Nama lengkap + Bidang: otomatis dari akun --}}
            <div class="akun-baris">
                <div class="akun-field {{ $errors->has('nama') ? 'has-error' : '' }}">
                    <label for="nama">Nama Lengkap <em>*</em></label>
                    <div class="akun-input">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M15 9h3M15 13h3M6 17c.5-2 5.5-2 6 0"/></svg>
                        <input type="text" id="nama" name="nama" value="{{ old('nama') }}" placeholder="-" readonly aria-describedby="err-nama">
                    </div>
                    <p class="akun-hint">Diambil dari akun.</p>
                    <p class="akun-error" id="err-nama" role="alert" @if (! $errors->has('nama')) hidden @endif>{!! $ikonSalah !!} <span>{{ $errors->first('nama') }}</span></p>
                </div>

                <div class="akun-field {{ $errors->has('id_bidang') ? 'has-error' : '' }}">
                    <label for="bidang_tampil">Bidang <em>*</em></label>
                    <div class="akun-input">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="12" height="18" rx="1"/><path d="M16 9h4v12h-4M8 7h4M8 11h4M8 15h4"/></svg>
                        <input type="text" id="bidang_tampil" value="" placeholder="-" readonly aria-describedby="err-id_bidang">
                        <input type="hidden" id="id_bidang" name="id_bidang" value="{{ old('id_bidang') }}">
                    </div>
                    <p class="akun-hint">Diambil dari akun.</p>
                    <p class="akun-error" id="err-id_bidang" role="alert" @if (! $errors->has('id_bidang')) hidden @endif>{!! $ikonSalah !!} <span>{{ $errors->first('id_bidang') }}</span></p>
                </div>
            </div>

            {{-- Nomor telepon --}}
            <div class="akun-field {{ $errors->has('nomor_telepon') ? 'has-error' : '' }}">
                <label for="nomor_telepon">Nomor Telepon <em>*</em></label>
                <div class="akun-input">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>
                    <input type="tel" id="nomor_telepon" name="nomor_telepon" inputmode="numeric" autocomplete="tel-national" maxlength="20"
                           value="{{ old('nomor_telepon') }}" placeholder="08112233445" aria-describedby="err-nomor_telepon" required>
                </div>
                <p class="akun-hint">Hanya angka, 8 sampai 15 digit.</p>
                <p class="akun-error" id="err-nomor_telepon" role="alert" @if (! $errors->has('nomor_telepon')) hidden @endif>{!! $ikonSalah !!} <span>{{ $errors->first('nomor_telepon') }}</span></p>
            </div>

            {{-- Data tambahan (opsional) --}}
            <details class="ang-more" @if ($tambahanBerisiError) open @endif>
                <summary>Data Tambahan (opsional)</summary>
                <div class="ang-more-body">

                    <h3>1. Data pribadi</h3>
                    <div class="akun-baris">
                        <div class="akun-field {{ $errors->has('nama_panggilan') ? 'has-error' : '' }}">
                            <label for="nama_panggilan">Nama Panggilan</label>
                            <div class="akun-input polos"><input type="text" id="nama_panggilan" name="nama_panggilan" maxlength="255" value="{{ old('nama_panggilan') }}" placeholder="Contoh: Ny. Wiwik Suryo"></div>
                            @error('nama_panggilan') <p class="akun-error">{!! $ikonSalah !!} <span>{{ $message }}</span></p> @enderror
                        </div>
                        <div class="akun-field {{ $errors->has('email') ? 'has-error' : '' }}">
                            <label for="email">Email</label>
                            <div class="akun-input polos"><input type="email" id="email" name="email" maxlength="255" value="{{ old('email') }}" placeholder="contoh@kemenkeu.go.id"></div>
                            @error('email') <p class="akun-error">{!! $ikonSalah !!} <span>{{ $message }}</span></p> @enderror
                        </div>
                        <div class="akun-field {{ $errors->has('tempat_lahir') ? 'has-error' : '' }}">
                            <label for="tempat_lahir">Tempat Lahir</label>
                            <div class="akun-input polos"><input type="text" id="tempat_lahir" name="tempat_lahir" maxlength="255" value="{{ old('tempat_lahir') }}" placeholder="Contoh: Jakarta"></div>
                            @error('tempat_lahir') <p class="akun-error">{!! $ikonSalah !!} <span>{{ $message }}</span></p> @enderror
                        </div>
                        <div class="akun-field {{ $errors->has('tanggal_lahir') ? 'has-error' : '' }}">
                            <label for="tanggal_lahir">Tanggal Lahir</label>
                            <div class="akun-input polos"><input type="date" id="tanggal_lahir" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}"></div>
                            @error('tanggal_lahir') <p class="akun-error">{!! $ikonSalah !!} <span>{{ $message }}</span></p> @enderror
                        </div>
                    </div>
                    <div class="akun-field {{ $errors->has('alamat_lengkap') ? 'has-error' : '' }}">
                        <label for="alamat_lengkap">Alamat Lengkap</label>
                        <div class="akun-input"><textarea id="alamat_lengkap" name="alamat_lengkap" maxlength="1000" placeholder="Alamat domisili lengkap...">{{ old('alamat_lengkap') }}</textarea></div>
                        @error('alamat_lengkap') <p class="akun-error">{!! $ikonSalah !!} <span>{{ $message }}</span></p> @enderror
                    </div>

                    <h3>2. Data suami</h3>
                    <div class="akun-baris">
                        <div class="akun-field {{ $errors->has('nama_suami') ? 'has-error' : '' }}">
                            <label for="nama_suami">Nama Suami</label>
                            <div class="akun-input polos"><input type="text" id="nama_suami" name="nama_suami" maxlength="255" value="{{ old('nama_suami') }}" placeholder="Nama lengkap suami"></div>
                            @error('nama_suami') <p class="akun-error">{!! $ikonSalah !!} <span>{{ $message }}</span></p> @enderror
                        </div>
                        <div class="akun-field {{ $errors->has('id_unit_kerja') ? 'has-error' : '' }}">
                            <label for="id_unit_kerja">Unit Kerja Suami</label>
                            <div class="akun-input polos">
                                <select id="id_unit_kerja" name="id_unit_kerja">
                                    <option value="">Pilih Unit Kerja</option>
                                    @foreach ($unitKerja as $unit)
                                        <option value="{{ $unit->id_unit_kerja }}" @selected(old('id_unit_kerja') == $unit->id_unit_kerja)>{{ $unit->nama_unit_kerja }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('id_unit_kerja') <p class="akun-error">{!! $ikonSalah !!} <span>{{ $message }}</span></p> @enderror
                        </div>
                    </div>

                    <h3>3. Pendidikan dan pekerjaan</h3>
                    <div class="akun-baris">
                        <div class="akun-field {{ $errors->has('tingkat_pendidikan') ? 'has-error' : '' }}">
                            <label for="tingkat_pendidikan">Tingkat Pendidikan Terakhir</label>
                            <div class="akun-input polos">
                                <select id="tingkat_pendidikan" name="tingkat_pendidikan">
                                    <option value="">Pilih Tingkat Pendidikan</option>
                                    @foreach ($tingkatPendidikan as $tingkat)
                                        <option value="{{ $tingkat }}" @selected(old('tingkat_pendidikan') === $tingkat)>{{ $tingkat }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('tingkat_pendidikan') <p class="akun-error">{!! $ikonSalah !!} <span>{{ $message }}</span></p> @enderror
                        </div>
                        <div class="akun-field {{ $errors->has('bidang_pendidikan') ? 'has-error' : '' }}">
                            <label for="bidang_pendidikan">Bidang Pendidikan</label>
                            <div class="akun-input polos"><input type="text" id="bidang_pendidikan" name="bidang_pendidikan" maxlength="255" value="{{ old('bidang_pendidikan') }}" placeholder="Contoh: Ekonomi Pembangunan"></div>
                            @error('bidang_pendidikan') <p class="akun-error">{!! $ikonSalah !!} <span>{{ $message }}</span></p> @enderror
                        </div>
                    </div>
                    <div class="akun-field {{ $errors->has('keahlian') ? 'has-error' : '' }}">
                        <label for="keahlian">Keahlian / Keterampilan yang Bisa Dibagikan</label>
                        <div class="akun-input"><textarea id="keahlian" name="keahlian" maxlength="1000" placeholder="Tuliskan keahlian atau keterampilan yang dapat dibagikan kepada anggota lain...">{{ old('keahlian') }}</textarea></div>
                        @error('keahlian') <p class="akun-error">{!! $ikonSalah !!} <span>{{ $message }}</span></p> @enderror
                    </div>
                    <div class="akun-baris">
                        <div class="akun-field {{ $errors->has('bidang_usaha') ? 'has-error' : '' }}">
                            <label for="bidang_usaha">Bidang Usaha yang Dimiliki</label>
                            <div class="akun-input polos"><input type="text" id="bidang_usaha" name="bidang_usaha" maxlength="255" value="{{ old('bidang_usaha') }}" placeholder="Kosongkan jika tidak memiliki bidang usaha"></div>
                            @error('bidang_usaha') <p class="akun-error">{!! $ikonSalah !!} <span>{{ $message }}</span></p> @enderror
                        </div>
                        <div class="akun-field {{ $errors->has('instansi_kerja') ? 'has-error' : '' }}">
                            <label for="instansi_kerja">Instansi Tempat Bekerja</label>
                            <div class="akun-input polos"><input type="text" id="instansi_kerja" name="instansi_kerja" maxlength="255" value="{{ old('instansi_kerja') }}" placeholder="Nama instansi/perusahaan tempat bekerja"></div>
                            @error('instansi_kerja') <p class="akun-error">{!! $ikonSalah !!} <span>{{ $message }}</span></p> @enderror
                        </div>
                    </div>

                    <h3>4. Kepengurusan</h3>
                    <div class="akun-baris">
                        <div class="akun-field">
                            <label for="posisi_tampil">Posisi di Kepengurusan</label>
                            <div class="akun-input polos"><input type="text" id="posisi_tampil" value="" placeholder="-" readonly></div>
                        </div>
                        <div class="akun-field">
                            <label for="bidang_kepengurusan">Bidang di Kepengurusan</label>
                            <div class="akun-input polos"><input type="text" id="bidang_kepengurusan" value="" placeholder="-" readonly></div>
                        </div>
                    </div>
                </div>
            </details>

            <div class="akun-form-aksi">
                <a href="{{ route('admin.anggota.index') }}" class="akun-btn-batal">← Batal</a>
                <button type="submit" id="btn-simpan" class="akun-btn-simpan">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
                    <span>Simpan</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var form   = document.getElementById('form-anggota');
    var akun   = document.getElementById('id_pengguna');
    var nama   = document.getElementById('nama');
    var idBid  = document.getElementById('id_bidang');
    var bidTam = document.getElementById('bidang_tampil');
    var posisi = document.getElementById('posisi_tampil');
    var bidKep = document.getElementById('bidang_kepengurusan');
    var telp   = document.getElementById('nomor_telepon');
    var btn    = document.getElementById('btn-simpan');

    // Pesan sama dengan kontrak T-21 bagian 5.3
    var PESAN = {
        akun: 'Akun wajib dipilih.',
        nama: 'Nama wajib diisi.',
        bidang: 'Bidang tidak tersedia.',
        telpKosong: 'Nomor telepon wajib diisi.',
        telpFormat: 'Nomor telepon harus 8 sampai 15 digit angka.'
    };

    function tampilkanError(input, errId, pesan) {
        var el = document.getElementById(errId);
        el.querySelector('span').textContent = pesan;   // textContent: aman dari XSS
        el.hidden = false;
        input.closest('.akun-field').classList.add('has-error');
        input.setAttribute('aria-invalid', 'true');
    }

    function bersihkanError(input, errId) {
        var el = document.getElementById(errId);
        el.querySelector('span').textContent = '';
        el.hidden = true;
        input.closest('.akun-field').classList.remove('has-error');
        input.removeAttribute('aria-invalid');
    }

    // Isi nama, bidang, dan posisi otomatis dari akun terpilih
    function isiDariAkun() {
        var opt = akun.options[akun.selectedIndex];
        var ada = opt && opt.value !== '';
        nama.value   = ada ? opt.dataset.nama : '';
        idBid.value  = ada ? opt.dataset.idBidang : '';
        bidTam.value = ada ? opt.dataset.bidang : '';
        bidKep.value = ada ? opt.dataset.bidang : '';
        posisi.value = ada ? opt.dataset.peran : '';
    }

    akun.addEventListener('change', function () {
        isiDariAkun();
        bersihkanError(akun, 'err-id_pengguna');
        bersihkanError(nama, 'err-nama');
        bersihkanError(bidTam, 'err-id_bidang');
    });

    telp.addEventListener('input', function () {
        bersihkanError(telp, 'err-nomor_telepon');
    });

    function validasi() {
        var pertamaSalah = null;
        function tandai(input) { if (!pertamaSalah) { pertamaSalah = input; } }

        if (akun.value === '') {
            tampilkanError(akun, 'err-id_pengguna', PESAN.akun);
            tandai(akun);
        } else {
            bersihkanError(akun, 'err-id_pengguna');
            if (nama.value.trim() === '') {
                tampilkanError(nama, 'err-nama', PESAN.nama);
                tandai(nama);
            }
            if (idBid.value === '') {
                tampilkanError(bidTam, 'err-id_bidang', PESAN.bidang);
                tandai(bidTam);
            }
        }

        var t = telp.value.trim();
        if (t === '') {
            tampilkanError(telp, 'err-nomor_telepon', PESAN.telpKosong);
            tandai(telp);
        } else if (!/^[0-9]{8,15}$/.test(t)) {
            tampilkanError(telp, 'err-nomor_telepon', PESAN.telpFormat);
            tandai(telp);
        } else {
            bersihkanError(telp, 'err-nomor_telepon');
        }

        if (pertamaSalah) { pertamaSalah.focus(); }
        return pertamaSalah === null;
    }

    form.addEventListener('submit', function (e) {
        if (!validasi()) {
            e.preventDefault();
            return;
        }
        btn.disabled = true;                              // cegah kirim ganda
        btn.querySelector('span').textContent = 'Menyimpan...';
    });

    // Pulihkan tampilan setelah redirect balik dari server (old input)
    isiDariAkun();
    if (document.getElementById('ang-summary')) {
        var salah = form.querySelector('.has-error input, .has-error select');
        if (salah) { salah.focus(); }
    }
})();
</script>
@endpush
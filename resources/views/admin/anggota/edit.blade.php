@extends('layouts.admin')

@section('title', 'Edit Anggota')

@php
    $tingkatPendidikan = ['SMA/SMK', 'D3', 'D4', 'S1 (Sarjana)', 'S2 (Magister)', 'S3 (Doktoral)'];
    $panggilan = preg_replace('/^(nyonya|ny)(\.\s*|\s+)/i', '', $anggota->nama_panggilan ?? '');
    $kolomTambahan = [
        'nama_panggilan',
        'tempat_lahir',
        'tanggal_lahir',
        'email',
        'alamat_lengkap',
        'nama_suami',
        'id_unit_kerja',
        'tingkat_pendidikan',
        'bidang_pendidikan',
        'keahlian',
        'bidang_usaha',
        'bekerja',
        'instansi_kerja',
    ];
    $tambahanBerisiError = collect($kolomTambahan)->contains(fn($k) => $errors->has($k));
@endphp

@push('styles')
    <style>
        .ang-lebar {
            width: auto;
            max-width: none;
            margin: 16px 16px 0;
        }

        @media (max-width: 768px) {
            .ang-lebar {
                margin: 16px 0 0;
            }
        }

        .akun-input.polos input,
        .akun-input.polos select {
            padding-left: 14px;
        }

        .akun-input input[readonly] {
            background: #f7f1ed;
            color: var(--coklat);
            cursor: not-allowed;
        }

        .akun-input textarea {
            width: 100%;
            min-height: 90px;
            padding: 12px 14px;
            font: 400 14px "Inter", system-ui, sans-serif;
            color: var(--ink);
            background: #fff;
            border: 1px solid #e9d8d0;
            border-radius: 8px;
            outline: none;
            resize: vertical;
        }

        .akun-input textarea:focus {
            border-color: #c2653d;
            box-shadow: 0 0 0 3px rgba(194, 101, 61, .16);
        }

        .akun-field.has-error .akun-input textarea {
            background: #fde2d6;
            border-color: #f4b99a;
        }

        .ang-prefix {
            position: relative;
        }

        .ang-prefix .ang-prefix-tag {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font: 400 14px "Inter", system-ui, sans-serif;
            color: var(--ink);
            pointer-events: none;
        }

        .akun-input.polos.ang-prefix input {
            padding-left: 44px;
        }

        .ang-more {
            margin: 8px 0 4px;
            border: 1px solid #f0ded4;
            border-radius: 10px;
            background: #fffaf6;
        }

        .ang-more>summary {
            cursor: pointer;
            padding: 14px 18px;
            font-size: 14px;
            font-weight: 600;
            color: #000;
            list-style: none;
        }

        .ang-more>summary::-webkit-details-marker {
            display: none;
        }

        .ang-more>summary::after {
            content: "\25BE";
            float: right;
            transition: transform .15s;
        }

        .ang-more[open]>summary::after {
            transform: rotate(180deg);
        }

        .ang-more-body {
            padding: 4px 20px 8px;
        }

        .ang-more h3 {
            margin: 18px 0 12px;
            font-size: 14px;
            font-weight: 600;
            color: #9a3f1c;
        }
    </style>
@endpush

@section('content')
    <div class="content akun-page">
        <div class="akun-form-card">
            <div class="akun-form-head">
                <div>
                    <h1>Edit Anggota</h1>
                    <p>Perbarui data anggota yang tersimpan.</p>
                </div>
                <span class="akun-chip">Formulir Anggota</span>
            </div>

            @if (session('error'))
                <div class="akun-alert-error ang-lebar" role="alert">
                    <div>
                        <strong>Data belum bisa diperbarui</strong>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @elseif ($errors->any())
                <div class="akun-alert-error ang-lebar" role="alert" id="ang-summary">
                    <div>
                        <strong>Terdapat {{ $errors->count() }} kesalahan pengisian formulir</strong>
                        <span>Data belum bisa disimpan. Periksa kolom yang ditandai merah.</span>
                    </div>
                </div>
            @else
                <div class="akun-info-box ang-lebar" role="note">
                    Nama lengkap, akun terkait, dan bidang tidak dapat diubah melalui formulir ini.
                    Data tambahan boleh dikosongkan.
                </div>
            @endif

            <form id="form-anggota" action="{{ route('admin.anggota.update', $anggota->id_anggota) }}" method="POST"
                class="akun-form" novalidate>
                @csrf
                @method('PUT')

                <div class="akun-form-judul">
                    <h2><i></i> Informasi Anggota</h2>
                    <span>✳ Wajib diisi</span>
                </div>

                <div class="akun-baris">
                    <div class="akun-field">
                        <label>Nama Lengkap</label>
                        <div class="akun-input polos">
                            <input type="text" value="{{ $anggota->nama_lengkap }}" readonly>
                        </div>
                    </div>
                    <div class="akun-field">
                        <label>Bidang</label>
                        <div class="akun-input polos">
                            <input type="text" value="{{ $namaBidang }}" readonly>
                        </div>
                    </div>
                </div>

                <div class="akun-field">
                    <label for="nomor_telepon">Nomor Telepon <em>*</em></label>
                    <div class="akun-input polos">
                        <input type="tel" id="nomor_telepon" name="nomor_telepon" inputmode="numeric"
                            autocomplete="tel-national" maxlength="20"
                            value="{{ old('nomor_telepon', $anggota->nomor_telepon) }}" placeholder="08112233445" required>
                    </div>
                    <p class="akun-hint">Hanya angka, 8 sampai 15 digit.</p>
                    @error('nomor_telepon')
                        <p class="akun-error"><x-icon name="x-circle" :size="14" /> <span>{{ $message }}</span></p>
                    @enderror
                </div>

                <details class="ang-more" @if ($tambahanBerisiError) open @endif>
                    <summary>Data Tambahan (opsional)</summary>
                    <div class="ang-more-body">

                        <h3>1. Data pribadi</h3>
                        <div class="akun-baris">
                            <div class="akun-field {{ $errors->has('nama_panggilan') ? 'has-error' : '' }}">
                                <label for="nama_panggilan">Nama Panggilan</label>
                                <div class="akun-input polos ang-prefix">
                                    <span class="ang-prefix-tag" aria-hidden="true">Ny.</span>
                                    <input type="text" id="nama_panggilan" name="nama_panggilan" maxlength="255"
                                        value="{{ old('nama_panggilan', $panggilan) }}" placeholder="Wiwik Suryo">
                                </div>
                                @error('nama_panggilan')
                                    <p class="akun-error"><x-icon name="x-circle" :size="14" />
                                        <span>{{ $message }}</span>
                                    </p>
                                @enderror
                            </div>
                            <div class="akun-field {{ $errors->has('email') ? 'has-error' : '' }}">
                                <label for="email">Email</label>
                                <div class="akun-input polos">
                                    <input type="email" id="email" name="email" maxlength="255"
                                        value="{{ old('email', $anggota->email) }}" placeholder="contoh@kemenkeu.go.id">
                                </div>
                                @error('email')
                                    <p class="akun-error"><x-icon name="x-circle" :size="14" />
                                        <span>{{ $message }}</span>
                                    </p>
                                @enderror
                            </div>
                            <div class="akun-field {{ $errors->has('tempat_lahir') ? 'has-error' : '' }}">
                                <label for="tempat_lahir">Tempat Lahir</label>
                                <div class="akun-input polos">
                                    <input type="text" id="tempat_lahir" name="tempat_lahir" maxlength="255"
                                        value="{{ old('tempat_lahir', $anggota->tempat_lahir) }}"
                                        placeholder="Contoh: Jakarta">
                                </div>
                                @error('tempat_lahir')
                                    <p class="akun-error"><x-icon name="x-circle" :size="14" />
                                        <span>{{ $message }}</span>
                                    </p>
                                @enderror
                            </div>
                            <div class="akun-field {{ $errors->has('tanggal_lahir') ? 'has-error' : '' }}">
                                <label for="tanggal_lahir">Tanggal Lahir</label>
                                <div class="akun-input polos">
                                    <input type="date" id="tanggal_lahir" name="tanggal_lahir"
                                        value="{{ old('tanggal_lahir', $anggota->tanggal_lahir) }}">
                                </div>
                                @error('tanggal_lahir')
                                    <p class="akun-error"><x-icon name="x-circle" :size="14" />
                                        <span>{{ $message }}</span>
                                    </p>
                                @enderror
                            </div>
                        </div>

                        <div class="akun-field {{ $errors->has('alamat_lengkap') ? 'has-error' : '' }}">
                            <label for="alamat_lengkap">Alamat Lengkap</label>
                            <div class="akun-input">
                                <textarea id="alamat_lengkap" name="alamat_lengkap" maxlength="1000" placeholder="Alamat domisili lengkap...">{{ old('alamat_lengkap', $anggota->alamat_lengkap) }}</textarea>
                            </div>
                            @error('alamat_lengkap')
                                <p class="akun-error"><x-icon name="x-circle" :size="14" />
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>

                        <h3>2. Data suami</h3>
                        <div class="akun-baris">
                            <div class="akun-field {{ $errors->has('nama_suami') ? 'has-error' : '' }}">
                                <label for="nama_suami">Nama Suami</label>
                                <div class="akun-input polos">
                                    <input type="text" id="nama_suami" name="nama_suami" maxlength="255"
                                        value="{{ old('nama_suami', $anggota->nama_suami) }}"
                                        placeholder="Nama lengkap suami">
                                </div>
                                @error('nama_suami')
                                    <p class="akun-error"><x-icon name="x-circle" :size="14" />
                                        <span>{{ $message }}</span>
                                    </p>
                                @enderror
                            </div>
                            <div class="akun-field {{ $errors->has('id_unit_kerja') ? 'has-error' : '' }}">
                                <label for="id_unit_kerja">Unit Kerja Suami</label>
                                <div class="akun-input polos">
                                    <select id="id_unit_kerja" name="id_unit_kerja">
                                        <option value="">Pilih Unit Kerja</option>
                                        @foreach ($unitKerja as $unit)
                                            <option value="{{ $unit->id_unit_kerja }}" @selected(old('id_unit_kerja', $anggota->id_unit_kerja) == $unit->id_unit_kerja)>
                                                {{ $unit->nama_unit_kerja }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('id_unit_kerja')
                                    <p class="akun-error"><x-icon name="x-circle" :size="14" />
                                        <span>{{ $message }}</span>
                                    </p>
                                @enderror
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
                                            <option value="{{ $tingkat }}" @selected(old('tingkat_pendidikan', $anggota->tingkat_pendidikan) === $tingkat)>
                                                {{ $tingkat }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('tingkat_pendidikan')
                                    <p class="akun-error"><x-icon name="x-circle" :size="14" />
                                        <span>{{ $message }}</span>
                                    </p>
                                @enderror
                            </div>
                            <div class="akun-field {{ $errors->has('bidang_pendidikan') ? 'has-error' : '' }}">
                                <label for="bidang_pendidikan">Bidang Pendidikan</label>
                                <div class="akun-input polos">
                                    <input type="text" id="bidang_pendidikan" name="bidang_pendidikan"
                                        maxlength="255"
                                        value="{{ old('bidang_pendidikan', $anggota->bidang_pendidikan) }}"
                                        placeholder="Contoh: Ekonomi Pembangunan">
                                </div>
                                @error('bidang_pendidikan')
                                    <p class="akun-error"><x-icon name="x-circle" :size="14" />
                                        <span>{{ $message }}</span>
                                    </p>
                                @enderror
                            </div>
                        </div>

                        <div class="akun-field {{ $errors->has('keahlian') ? 'has-error' : '' }}">
                            <label for="keahlian">Keahlian / Keterampilan / Pengetahuan yang Bisa Dibagikan</label>
                            <div class="akun-input">
                                <textarea id="keahlian" name="keahlian" maxlength="1000"
                                    placeholder="Tuliskan keahlian, keterampilan, atau pengetahuan yang dapat dibagikan kepada anggota lain...">{{ old('keahlian', $anggota->keahlian) }}</textarea>
                            </div>
                            @error('keahlian')
                                <p class="akun-error"><x-icon name="x-circle" :size="14" />
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>

                        <div class="akun-baris">
                            <div class="akun-field {{ $errors->has('bidang_usaha') ? 'has-error' : '' }}">
                                <label for="bidang_usaha">Bidang Usaha yang Dimiliki</label>
                                <div class="akun-input polos">
                                    <input type="text" id="bidang_usaha" name="bidang_usaha" maxlength="255"
                                        value="{{ old('bidang_usaha', $anggota->bidang_usaha) }}"
                                        placeholder="Kosongkan jika tidak memiliki bidang usaha">
                                </div>
                                @error('bidang_usaha')
                                    <p class="akun-error"><x-icon name="x-circle" :size="14" />
                                        <span>{{ $message }}</span>
                                    </p>
                                @enderror
                            </div>
                            <div class="akun-field {{ $errors->has('bekerja') ? 'has-error' : '' }}">
                                <label for="bekerja">Bekerja</label>
                                <div class="akun-input polos">
                                    <select id="bekerja" name="bekerja">
                                        <option value="">Pilih</option>
                                        <option value="Ya" @selected(old('bekerja', $anggota->bekerja) === 'Ya')>Ya</option>
                                        <option value="Tidak" @selected(old('bekerja', $anggota->bekerja) === 'Tidak')>Tidak</option>
                                    </select>
                                </div>
                                @error('bekerja')
                                    <p class="akun-error"><x-icon name="x-circle" :size="14" />
                                        <span>{{ $message }}</span>
                                    </p>
                                @enderror
                            </div>
                        </div>

                        <div class="akun-field {{ $errors->has('instansi_kerja') ? 'has-error' : '' }}">
                            <label for="instansi_kerja">Instansi Tempat Bekerja</label>
                            <div class="akun-input polos">
                                <input type="text" id="instansi_kerja" name="instansi_kerja" maxlength="255"
                                    value="{{ old('instansi_kerja', $anggota->instansi_kerja) }}"
                                    placeholder="Nama instansi/perusahaan tempat bekerja">
                            </div>
                            @error('instansi_kerja')
                                <p class="akun-error"><x-icon name="x-circle" :size="14" />
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>
                    </div>
                </details>

                <div class="akun-form-aksi">
                    <a href="{{ route('admin.anggota.index') }}" class="akun-btn-batal">← Batal</a>
                    <button type="submit" id="btn-simpan" class="akun-btn-simpan">
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            'use strict';

            var form = document.getElementById('form-anggota');
            var btn = document.getElementById('btn-simpan');
            var bekerja = document.getElementById('bekerja');
            var instansi = document.getElementById('instansi_kerja');

            function aturInstansi() {
                var tidak = bekerja.value === 'Tidak';
                if (tidak) instansi.value = '';
                instansi.disabled = tidak;
            }

            bekerja.addEventListener('change', aturInstansi);
            aturInstansi();

            var panggilan = document.getElementById('nama_panggilan');

            function bersihkanPanggilan(v) {
                return v.replace(/\s+/g, ' ').trim().replace(/^(nyonya|ny)(\.\s*|\s+)/i, '');
            }
            panggilan.addEventListener('blur', function() {
                panggilan.value = bersihkanPanggilan(panggilan.value);
            });
            panggilan.value = bersihkanPanggilan(panggilan.value);

            form.addEventListener('submit', function() {
                btn.disabled = true;
                btn.querySelector('span').textContent = 'Menyimpan...';
            });

            if (document.getElementById('ang-summary')) {
                var salah = form.querySelector('.has-error input, .has-error select, .has-error textarea');
                if (salah) salah.focus();
            }
        })();
    </script>
@endpush

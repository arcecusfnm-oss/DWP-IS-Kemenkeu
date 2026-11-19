@extends('layouts.bidang')

@section('title', 'Ubah Program Kerja')

@section('content')
@php
    $err = collect($errors->messages())->map(fn ($m) => $m[0])->all();

    // Tanggal dari DB bisa berformat datetime; input date butuh Y-m-d.
    $tglMulai   = substr((string) $program->tanggal_mulai, 0, 10);
    $tglSelesai = substr((string) $program->tanggal_selesai, 0, 10);

    // Anggaran decimal(15,2): buang nol di belakang koma agar tampil "15000000".
    $anggaran = $program->anggaran;
    if ($anggaran !== null && str_contains((string) $anggaran, '.')) {
        $anggaran = rtrim(rtrim((string) $anggaran, '0'), '.');
    }
@endphp

<div class="content bidang-page">

    {{-- HEADER --}}
    <div class="bidang-header-card">
        <div class="bidang-header">
            <div>
                <div class="program-header-info">
                    <h1>Ubah Program Kerja</h1>
                    <span class="program-bidang-badge">{{ $namaBidang ?? 'Bidang' }}</span>
                </div>
                <p>Perbarui informasi program kerja Bidang {{ $namaBidang ?? 'Anda' }}.</p>
            </div>

            <span class="pk-chip-info">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                Semua kolom bertanda (*) wajib dilengkapi.
            </span>
        </div>
    </div>

    @if (session('galat'))
        <div class="bidang-alert">{{ session('galat') }}</div>
    @endif

    <form action="{{ route('program-kerja.update', ['id' => $program->id_program_kerja]) }}" method="POST"
          class="akun-form pk-form {{ $err ? 'ada-error' : '' }}" novalidate>
        @csrf
        @method('PUT')

        <div class="akun-form-judul">
            <h2><i></i> Informasi Program Kerja</h2>
            <span>* Kolom wajib diisi</span>
        </div>

        @if ($err)
            <div class="akun-alert-error" role="alert">
                <x-icon name="x-circle" :size="22" />
                <div>
                    <strong>Periksa kembali isian Anda.</strong>
                    <span>Terdapat beberapa kolom wajib yang belum diisi dengan benar.</span>
                </div>
            </div>
        @endif

        {{-- Bidang (hanya tampilan; bidang tidak bisa diubah) --}}
        <div class="akun-field">
            <label for="bidang_tampil">Bidang <em>*</em></label>
            <div class="akun-input akun-readonly">
                <input type="text" id="bidang_tampil" value="{{ $namaBidang }}" disabled>
                <svg class="akun-lock" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            </div>
            <p class="akun-hint">Bidang tidak dapat diubah.</p>
        </div>

        {{-- Nama program --}}
        <div class="akun-field {{ isset($err['nama_program']) ? 'has-error' : '' }}">
            <label for="nama_program">Nama Program <em>*</em></label>
            <div class="akun-input">
                <input type="text" id="nama_program" name="nama_program" maxlength="255"
                       placeholder="Contoh: Pelatihan Literasi Keuangan Keluarga"
                       value="{{ old('nama_program', $program->nama_program) }}">
            </div>
            @isset($err['nama_program'])
                <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['nama_program'] }}</p>
            @endisset
        </div>

        {{-- Tujuan --}}
        <div class="akun-field {{ isset($err['tujuan']) ? 'has-error' : '' }}">
            <label for="tujuan">Tujuan <em>*</em></label>
            <div class="akun-input">
                <textarea id="tujuan" name="tujuan" maxlength="5000" rows="4"
                          placeholder="Jelaskan tujuan dan gambaran singkat program">{{ old('tujuan', $program->tujuan) }}</textarea>
            </div>
            @isset($err['tujuan'])
                <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['tujuan'] }}</p>
            @endisset
        </div>

        {{-- Sasaran peserta + metode --}}
        <div class="akun-baris">
            <div class="akun-field {{ isset($err['sasaran_peserta']) ? 'has-error' : '' }}">
                <label for="sasaran_peserta">Sasaran Peserta</label>
                <div class="akun-input">
                    <input type="text" id="sasaran_peserta" name="sasaran_peserta" maxlength="255"
                           placeholder="Contoh: Anggota DWP dan keluarga"
                           value="{{ old('sasaran_peserta', $program->sasaran_peserta) }}">
                </div>
                @isset($err['sasaran_peserta'])
                    <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['sasaran_peserta'] }}</p>
                @endisset
            </div>

            <div class="akun-field {{ isset($err['metode']) ? 'has-error' : '' }}">
                <label for="metode">Metode</label>
                <div class="akun-input">
                    <input type="text" id="metode" name="metode" maxlength="255"
                           placeholder="Contoh: Seminar dan diskusi"
                           value="{{ old('metode', $program->metode) }}">
                </div>
                @isset($err['metode'])
                    <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['metode'] }}</p>
                @endisset
            </div>
        </div>

        {{-- Tanggal mulai + selesai --}}
        <div class="akun-baris">
            <div class="akun-field {{ isset($err['tanggal_mulai']) ? 'has-error' : '' }}">
                <label for="tanggal_mulai">Tanggal Mulai <em>*</em></label>
                <div class="akun-input">
                    <input type="date" id="tanggal_mulai" name="tanggal_mulai"
                           value="{{ old('tanggal_mulai', $tglMulai) }}">
                </div>
                @isset($err['tanggal_mulai'])
                    <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['tanggal_mulai'] }}</p>
                @endisset
            </div>

            <div class="akun-field {{ isset($err['tanggal_selesai']) ? 'has-error' : '' }}">
                <label for="tanggal_selesai">Tanggal Selesai <em>*</em></label>
                <div class="akun-input">
                    <input type="date" id="tanggal_selesai" name="tanggal_selesai"
                           value="{{ old('tanggal_selesai', $tglSelesai) }}">
                </div>
                @isset($err['tanggal_selesai'])
                    <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['tanggal_selesai'] }}</p>
                @endisset
            </div>
        </div>

        {{-- Tempat + anggaran --}}
        <div class="akun-baris">
            <div class="akun-field {{ isset($err['tempat']) ? 'has-error' : '' }}">
                <label for="tempat">Tempat</label>
                <div class="akun-input">
                    <input type="text" id="tempat" name="tempat" maxlength="255"
                           placeholder="Contoh: Gedung Djuanda I Kemenkeu / Hybrid via Zoom"
                           value="{{ old('tempat', $program->tempat) }}">
                </div>
                @isset($err['tempat'])
                    <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['tempat'] }}</p>
                @endisset
            </div>

            <div class="akun-field {{ isset($err['anggaran']) ? 'has-error' : '' }}">
                <label for="anggaran">Anggaran (Rp)</label>
                <div class="akun-input akun-rp">
                    <span class="akun-prefix">Rp</span>
                    <input type="number" id="anggaran" name="anggaran" min="0" step="0.01"
                           inputmode="decimal" placeholder="15000000"
                           value="{{ old('anggaran', $anggaran) }}">
                </div>
                @isset($err['anggaran'])
                    <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['anggaran'] }}</p>
                @else
                    <p class="akun-hint">Isi angka saja tanpa titik (contoh: 15000000)</p>
                @endisset
            </div>
        </div>

        {{-- Keterangan --}}
        <div class="akun-field {{ isset($err['keterangan']) ? 'has-error' : '' }}">
            <label for="keterangan">Keterangan</label>
            <div class="akun-input">
                <textarea id="keterangan" name="keterangan" maxlength="2000" rows="4"
                          placeholder="Catatan atau rincian tambahan (opsional)">{{ old('keterangan', $program->keterangan) }}</textarea>
            </div>
            @isset($err['keterangan'])
                <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['keterangan'] }}</p>
            @endisset
        </div>

        <div class="akun-form-aksi">
            <a href="{{ route('program-kerja.bidang-saya') }}" class="akun-btn-batal">Batal</a>
            <button type="submit" class="akun-btn-simpan">Simpan Perubahan</button>
        </div>
    </form>
</div>
@endsection
@extends('layouts.bidang')

@section('title', 'Tambah Program Kerja')

@section('content')
@php
    $err = collect($errors->messages())->map(fn ($m) => $m[0])->all();
@endphp

<div class="content bidang-page">

    {{-- HEADER --}}
    <div class="bidang-header-card">
        <div class="bidang-header">
            <div>
                <div class="program-header-info">
                    <h1>Tambah Program Kerja</h1>
                    <span class="program-bidang-badge">{{ $namaBidang ?? 'Bidang' }}</span>
                </div>
                <p>Formulir penambahan inisiatif program kerja baru untuk Bidang {{ $namaBidang ?? 'Anda' }}.</p>
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

    <form action="{{ route('program-kerja.store') }}" method="POST"
          class="akun-form pk-form {{ $err ? 'ada-error' : '' }}" novalidate>
        @csrf

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

        {{-- Bidang (hanya tampilan; bidang asli & status awal Diajukan diatur di server) --}}
        <div class="akun-field">
            <label for="bidang_tampil">Bidang <em>*</em></label>
            <div class="akun-input akun-readonly">
                <input type="text" id="bidang_tampil" value="{{ $namaBidang }}" disabled>
                <svg class="akun-lock" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            </div>
            <p class="akun-hint">Diisi otomatis sesuai akun Anda dengan status awal Diajukan.</p>
        </div>

        {{-- 1. Proker / Kegiatan --}}
        <div class="akun-field {{ isset($err['nama_program']) ? 'has-error' : '' }}">
            <label for="nama_program">Proker / Kegiatan <em>*</em></label>
            <div class="akun-input">
                <input type="text" id="nama_program" name="nama_program" maxlength="255"
                       placeholder="Contoh: Pelatihan Literasi Keuangan Keluarga" value="{{ old('nama_program') }}">
            </div>
            @isset($err['nama_program'])
                <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['nama_program'] }}</p>
            @endisset
        </div>

        {{-- 2. Tujuan --}}
        <div class="akun-field {{ isset($err['tujuan']) ? 'has-error' : '' }}">
            <label for="tujuan">Tujuan <em>*</em></label>
            <div class="akun-input">
                <textarea id="tujuan" name="tujuan" maxlength="5000" rows="4"
                          placeholder="Jelaskan tujuan dan gambaran singkat program">{{ old('tujuan') }}</textarea>
            </div>
            @isset($err['tujuan'])
                <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['tujuan'] }}</p>
            @endisset
        </div>

        {{-- 3. Sasaran Peserta + 4. Metode --}}
        <div class="akun-baris">
            <div class="akun-field {{ isset($err['sasaran_peserta']) ? 'has-error' : '' }}">
                <label for="sasaran_peserta">Sasaran Peserta <em>*</em></label>
                <div class="akun-input">
                    <input type="text" id="sasaran_peserta" name="sasaran_peserta" maxlength="255"
                           placeholder="Contoh: Anggota DWP dan keluarga" value="{{ old('sasaran_peserta') }}">
                </div>
                @isset($err['sasaran_peserta'])
                    <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['sasaran_peserta'] }}</p>
                @endisset
            </div>

            <div class="akun-field {{ isset($err['metode']) ? 'has-error' : '' }}">
                <label for="metode">Metode <em>*</em></label>
                <div class="akun-input">
                    <input type="text" id="metode" name="metode" maxlength="255"
                           placeholder="Contoh: Seminar dan diskusi" value="{{ old('metode') }}">
                </div>
                @isset($err['metode'])
                    <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['metode'] }}</p>
                @endisset
            </div>
        </div>

        {{-- 5. Jadwal (Tanggal Mulai + Tanggal Selesai) --}}
        <div class="akun-baris">
            <div class="akun-field {{ isset($err['tanggal_mulai']) ? 'has-error' : '' }}">
                <label for="tanggal_mulai">Tanggal Mulai (Jadwal) <em>*</em></label>
                <div class="akun-input">
                    <input type="date" id="tanggal_mulai" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}">
                </div>
                @isset($err['tanggal_mulai'])
                    <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['tanggal_mulai'] }}</p>
                @endisset
            </div>

            <div class="akun-field {{ isset($err['tanggal_selesai']) ? 'has-error' : '' }}">
                <label for="tanggal_selesai">Tanggal Selesai (Jadwal) <em>*</em></label>
                <div class="akun-input">
                    <input type="date" id="tanggal_selesai" name="tanggal_selesai" value="{{ old('tanggal_selesai') }}">
                </div>
                @isset($err['tanggal_selesai'])
                    <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['tanggal_selesai'] }}</p>
                @endisset
            </div>
        </div>

        {{-- 6. Tempat + 7. Anggaran (Rp) --}}
        <div class="akun-baris">
            <div class="akun-field {{ isset($err['tempat']) ? 'has-error' : '' }}">
                <label for="tempat">Tempat <em>*</em></label>
                <div class="akun-input">
                    <input type="text" id="tempat" name="tempat" maxlength="255"
                           placeholder="Contoh: Gedung Djuanda I Kemenkeu / Hybrid via Zoom" value="{{ old('tempat') }}">
                </div>
                @isset($err['tempat'])
                    <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['tempat'] }}</p>
                @endisset
            </div>

            <div class="akun-field {{ isset($err['anggaran']) ? 'has-error' : '' }}">
                <label for="anggaran">Anggaran (Rp) <em>*</em></label>
                <div class="akun-input akun-rp">
                    <span class="akun-prefix">Rp</span>
                    <input type="number" id="anggaran" name="anggaran" min="0" step="0.01"
                           inputmode="decimal" placeholder="15000000" value="{{ old('anggaran') }}">
                </div>
                @isset($err['anggaran'])
                    <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['anggaran'] }}</p>
                @else
                    <p class="akun-hint">Isi angka saja tanpa titik (contoh: 15000000)</p>
                @endisset
            </div>
        </div>

        {{-- 8. Keterangan (Opsional) --}}
        <div class="akun-field {{ isset($err['keterangan']) ? 'has-error' : '' }}">
            <label for="keterangan">Keterangan (Opsional)</label>
            <div class="akun-input">
                <textarea id="keterangan" name="keterangan" maxlength="2000" rows="4"
                          placeholder="Rincian anggaran atau catatan tambahan (opsional)">{{ old('keterangan') }}</textarea>
            </div>
            @isset($err['keterangan'])
                <p class="akun-error"><x-icon name="x-circle" :size="14" /> {{ $err['keterangan'] }}</p>
            @endisset
        </div>

        <div class="akun-form-aksi">
            <a href="{{ route('program-kerja.bidang-saya') }}" class="akun-btn-batal">Batal</a>
            <button type="submit" class="akun-btn-simpan">Simpan</button>
        </div>
    </form>
</div>
@endsection
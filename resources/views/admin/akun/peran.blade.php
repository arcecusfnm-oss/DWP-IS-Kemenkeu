@extends('layouts.admin')

@section('content')
@php
    $err = collect($errors->messages())->map(fn ($m) => $m[0])->all();
    $ikonSalah = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>';
@endphp

<div class="content akun-page">
    <div class="akun-form-card">

        <div class="akun-form-head">
            <div>
                <h1>Ubah Peran</h1>
                <p>Tetapkan peran baru untuk akun pengguna.</p>
            </div>
            <span class="akun-chip">Hak Akses</span>
        </div>

        @if ($err)
            <div class="akun-alert-error">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <div>
                    <strong>Peran belum dapat diubah</strong>
                    <span>Silakan periksa pilihan peran di bawah ini, lalu coba lagi.</span>
                </div>
            </div>
        @else
            <div class="akun-info-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                Satu akun hanya memiliki satu peran. Peran baru menggantikan peran lama, dan hak akses berlaku saat pengguna masuk berikutnya.
            </div>
        @endif

        <form action="{{ route('admin.akun.peran.update', $akun->id_pengguna) }}" method="POST" class="akun-form {{ $err ? 'ada-error' : '' }}" novalidate>
            @csrf
            @method('PUT')

            <div class="akun-form-judul">
                <h2><i></i> Penetapan Peran</h2>
                <span>✳ Wajib diisi</span>
            </div>

            {{-- Info akun (hanya tampil) --}}
            <div class="akun-baris">
                <div class="akun-field">
                    <label for="nama">Nama Lengkap</label>
                    <div class="akun-input">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M15 9h3M15 13h3M6 17c.5-2 5.5-2 6 0"/></svg>
                        <input type="text" id="nama" value="{{ $akun->nama_lengkap }}" disabled>
                    </div>
                </div>
                <div class="akun-field">
                    <label for="username">Nama Pengguna</label>
                    <div class="akun-input">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/></svg>
                        <input type="text" id="username" value="{{ $akun->nama_pengguna }}" disabled>
                    </div>
                </div>
            </div>

            <div class="akun-field">
                <label for="peranSaatIni">Peran Saat Ini</label>
                <div class="akun-input">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><circle cx="12" cy="10" r="2"/></svg>
                    <input type="text" id="peranSaatIni" value="{{ $akun->nama_peran }}" disabled>
                </div>
            </div>

            {{-- Peran baru --}}
            <div class="akun-field {{ isset($err['peran']) ? 'has-error' : '' }}">
                <label for="peran">Peran Baru <em>*</em></label>
                <div class="akun-input">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                    <select id="peran" name="peran">
                        <option value="">Pilih Peran</option>
                        @foreach ($daftarPeran as $p)
                            <option value="{{ $p }}" @selected(old('peran', $akun->nama_peran) === $p)>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                @isset($err['peran'])
                    <p class="akun-error">{!! $ikonSalah !!} {{ $err['peran'] }}</p>
                @endisset
            </div>

            <div class="akun-form-aksi">
                <a href="{{ route('admin.akun.index') }}" class="akun-btn-batal">← Batal</a>
                <button type="submit" class="akun-btn-simpan">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
                    Simpan Peran
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
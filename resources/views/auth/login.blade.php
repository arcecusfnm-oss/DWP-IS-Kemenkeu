@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<div class="latar-batik">
    <main class="kartu-masuk">
        <div class="kartu-kepala">
            <div class="logo-wrap">
                <img src="{{ asset('images/logo-dwp.png') }}" alt="Logo Dharma Wanita Persatuan" class="logo-masuk">
            </div>
            <h1 class="judul-masuk" style="padding-top: 20px;">DWP-IS Kemenkeu</h1>
            <p class="subjudul-masuk">Sistem Informasi Dharma Wanita Persatuan Kementerian Keuangan</p>
        </div>

        {{-- Pesan umum dari server: kredensial salah atau akun tidak aktif (AC B-001 no. 2 dan 3) --}}
        @if ($errors->has('login'))
            <div class="pesan pesan-error" role="alert">{{ $errors->first('login') }}</div>
        @endif

        {{-- Pesan terkunci setelah 5 kali gagal (AC B-001 no. 6) --}}
        @if ($errors->has('throttle'))
            <div class="pesan pesan-peringatan" role="alert">{{ $errors->first('throttle') }}</div>
        @endif

        <form method="POST" action="{{ url('/login') }}" id="form-masuk" novalidate>
            @csrf

            <div class="isian">
                <label for="username">Nama Pengguna</label>
                <input
                    id="username"
                    name="username"
                    type="text"
                    value="{{ old('username') }}"
                    placeholder="Masukkan nama pengguna"
                    autocomplete="username"
                    maxlength="50"
                    required
                    aria-describedby="username-error"
                    @class(['tidak-valid' => $errors->has('username')])
                >
                <p class="teks-error" id="username-error">@error('username'){{ $message }}@enderror</p>
            </div>

            <div class="isian">
                <label for="password">Kata Sandi</label>
                <div class="isian-sandi">
                    <input
                        id="password"
                        name="password"
                        type="password"
                        placeholder="Masukkan kata sandi"
                        autocomplete="current-password"
                        maxlength="100"
                        required
                        aria-describedby="password-error"
                        @class(['tidak-valid' => $errors->has('password')])
                    >
                    <button type="button" class="tombol-lihat" id="tombol-lihat" aria-label="Tampilkan kata sandi">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
                <p class="teks-error" id="password-error">@error('password'){{ $message }}@enderror</p>
            </div>

            <button type="submit" class="tombol-utama" @disabled($errors->has('throttle'))>Masuk</button>
        </form>

        <p class="teks-bantuan">Lupa kata sandi? <strong>Hubungi Admin Sekretariat</strong></p>
        <a href="{{ url('/') }}" class="tautan-kembali">&larr; Kembali ke Beranda</a>
    </main>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/masuk.js') }}"></script>
@endpush
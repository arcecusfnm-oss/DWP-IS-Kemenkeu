@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<div class="latar-batik">
    <main class="kartu-masuk">
        <div class="kartu-kepala">
            <div class="logo-wrap">
                <img
                    src="{{ asset('images/logo-dwp.png') }}"
                    alt="Logo Dharma Wanita Persatuan"
                    class="logo-masuk">
            </div>

            <h1 class="judul-masuk" style="padding-top: 20px;">
                DWP-IS Kemenkeu
            </h1>

            <p class="subjudul-masuk">
                Sistem Informasi Dharma Wanita Persatuan Kementerian Keuangan
            </p>
        </div>

        {{-- Pesan error: kredensial salah atau akun nonaktif --}}
        @if ($errors->has('login'))
        <div class="pesan pesan-error pesan-dengan-ikon" role="alert">
            <span class="pesan-teks">{{ $errors->first('login') }}</span>

            <svg
                class="ikon-warning ikon-warning-merah"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true">
                <path d="M10.3 3.9 2.5 17.4A2 2 0 0 0 4.2 20h15.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
                <path d="M12 9v4" />
                <path d="M12 16.5h.01" />
            </svg>
        </div>
        @endif

        {{-- Pesan pembatasan percobaan login --}}
        @php
        $throttleUntil = session('login_throttle_until');
        $isLoginThrottled = $throttleUntil !== null
        && (int) $throttleUntil > now()->timestamp;
        @endphp
        @if ($errors->has('throttle') || $isLoginThrottled)
        <div class="pesan pesan-peringatan pesan-dengan-ikon" role="alert">
            <span class="pesan-teks">
                Terlalu banyak percobaan masuk.
                Silakan coba lagi dalam 5 menit.
            </span>

            <svg
                class="ikon-warning ikon-warning-kuning"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true">
                <path d="M10.3 3.9 2.5 17.4A2 2 0 0 0 4.2 20h15.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
                <path d="M12 9v4" />
                <path d="M12 16.5h.01" />
            </svg>
        </div>
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
                    @class(['tidak-valid'=> $errors->has('username')])
                >

                <p class="teks-error" id="username-error">
                    @error('username')
                    {{ $message }}
                    @enderror
                </p>
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
                        @class(['tidak-valid'=> $errors->has('password')])
                    >

                    <button
                        type="button"
                        class="tombol-lihat"
                        id="tombol-lihat"
                        aria-label="Tampilkan kata sandi">
                        <svg
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>

                <p class="teks-error" id="password-error">
                    @error('password')
                    {{ $message }}
                    @enderror
                </p>
            </div>

            <button
                type="submit"
                class="tombol-utama"
                @disabled($errors->has('throttle') || $isLoginThrottled)
                >
                Masuk
            </button>
        </form>

        <p class="teks-bantuan">
            Lupa kata sandi?
            <strong>Hubungi Admin Sekretariat</strong>
        </p>

        <a href="{{ url('/') }}" class="tautan-kembali">
            &larr; Kembali ke Beranda
        </a>
    </main>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/masuk.js') }}"></script>
@endpush

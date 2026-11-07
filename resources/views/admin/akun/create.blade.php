@extends('layouts.admin')

@section('content')
@php
    // DEMO: buka /admin/akun/tambah?demo=error buat lihat state error.
    // Nanti ganti pakai $errors bawaan Laravel (@error('username') dst).
    $demoError = false;
    $err = collect($errors->messages())->map(fn ($m) => $m[0])->all();

    $ikonSalah = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>';
@endphp

<div class="content akun-page">
    <div class="akun-form-card">

        <div class="akun-form-head">
            <div>
                <h1>Buat Akun</h1>
                <p>Lengkapi data akun pengguna baru.</p>
            </div>
            <span class="akun-chip">Formulir Pengguna</span>
        </div>

        @if ($err)
            <div class="akun-alert-error">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <div>
                    <strong>Terdapat {{ count($err) }} kesalahan pengisian formulir</strong>
                    <span>Silakan periksa dan perbaiki isian data di bawah ini sebelum melanjutkan penyimpanan akun.</span>
                </div>
            </div>
        @else
            <div class="akun-info-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                Semua bidang bertanda bintang (*) wajib diisi untuk registrasi akun kepengurusan Dharma Wanita Persatuan Kemenkeu RI.
            </div>
        @endif

        {{-- Ganti action="#" jadi route store pas backend udah ada, terus tambahin @csrf --}}
        <form action="{{ route('admin.akun.store') }}" method="POST" class="akun-form {{ $err ? 'ada-error' : '' }}" novalidate>
        @csrf

            <div class="akun-form-judul">
                <h2><i></i> Informasi Kredensial &amp; Penugasan</h2>
                <span>✳ Wajib diisi</span>
            </div>

            {{-- Nama lengkap --}}
            <div class="akun-field {{ isset($err['nama']) ? 'has-error' : '' }}">
                <label for="nama">Nama Lengkap <em>*</em></label>
                <div class="akun-input {{ $demoError ? 'valid' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M15 9h3M15 13h3M6 17c.5-2 5.5-2 6 0"/></svg>
                    <input type="text" id="nama" name="nama" placeholder="Masukkan nama lengkap" value="{{ old('nama') }}">
                    @if ($demoError)
                        <svg class="status ok" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
                    @endif
                </div>
                @isset($err['nama'])
                    <p class="akun-error">{!! $ikonSalah !!} {{ $err['nama'] }}</p>
                @endisset
                @if ($demoError)
                    <p class="akun-hint">Format nama sesuai dengan kartu identitas pegawai/anggota DWP.</p>
                @endif
            </div>

            {{-- Nama pengguna --}}
            <div class="akun-field {{ isset($err['username']) ? 'has-error' : '' }}">
                <label for="username">Nama Pengguna <em>*</em></label>
                <div class="akun-input">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/></svg>
                    <input type="text" id="username" name="username" placeholder="Masukkan nama pengguna unik" value="{{ old('username') }}">
                    @isset($err['username'])
                        <svg class="status" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                    @endisset
                </div>
                @isset($err['username'])
                    <p class="akun-error">{!! $ikonSalah !!} {{ $err['username'] }}</p>
                @endisset
            </div>

            {{-- Peran + Bidang --}}
            <div class="akun-baris">
                <div class="akun-field {{ isset($err['peran']) ? 'has-error' : '' }}">
                    <label for="peran">Peran <em>*</em></label>
                    <div class="akun-input">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><circle cx="12" cy="10" r="2"/></svg>
                        <select id="peran" name="peran">
                            <option value="">Pilih Peran</option>
                            @foreach ($daftarPeran as $p)
                                <option value="{{ $p }}" @selected(old('peran') === $p)>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                    @isset($err['peran'])
                        <p class="akun-error">{!! $ikonSalah !!} {{ $err['peran'] }}</p>
                    @endisset
                </div>

                <div class="akun-field {{ isset($err['bidang']) ? 'has-error' : '' }}">
                    <label for="bidang">Bidang <em>*</em></label>
                    <div class="akun-input">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="12" height="18" rx="1"/><path d="M16 9h4v12h-4M8 7h4M8 11h4M8 15h4"/></svg>
                        <select id="bidang" name="bidang">
                            <option value="">Pilih peran dulu</option>
                        </select>
                    </div>
                    @isset($err['bidang'])
                        <p class="akun-error">{!! $ikonSalah !!} {{ $err['bidang'] }}</p>
                    @endisset
                </div>
            </div>

            {{-- Kata sandi awal --}}
            <div class="akun-field {{ isset($err['password']) ? 'has-error' : '' }}">
                <label for="password">Kata Sandi Awal <em>*</em></label>
                <div class="akun-input">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    <input type="password" id="password" name="password" placeholder="Masukkan kata sandi awal" autocomplete="new-password" maxlength="100">
                    <button type="button" id="togglePassword" aria-label="Tampilkan kata sandi"
                        style="position:absolute; top:0; right:0; width:46px; height:46px; display:grid; place-items:center; padding:0; color:#7a5c52; opacity:.7;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                    @isset($err['password'])
                        <svg class="status" style="right:50px" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                    @endisset
                </div>
                @isset($err['password'])
                    <p class="akun-error">{!! $ikonSalah !!} {{ $err['password'] }}</p>
                @else
                    <p class="akun-hint">Minimal 8 karakter.</p>
                @endisset
            </div>

            <div class="akun-form-aksi">
                <a href="{{ route('admin.akun.index') }}" class="akun-btn-batal">← Batal</a>
                <button type="submit" class="akun-btn-simpan">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
                    Simpan Akun
                </button>
            </div>
        </form>
    </div>
</div>
<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const input = document.getElementById('password');
        const tampil = input.type === 'password';
        input.type = tampil ? 'text' : 'password';
        this.setAttribute('aria-label', tampil ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    });
</script>
<div id="dwpData" hidden
     data-peta="{{ json_encode($petaBidang) }}"
     data-penuh="{{ json_encode($penuh) }}"
     data-lama="{{ old('bidang') }}">
</div>
<script>
(function () {
    const data   = document.getElementById('dwpData').dataset;
    const peta   = JSON.parse(data.peta);
    const penuh  = JSON.parse(data.penuh);
    const lama   = data.lama;
    const peran  = document.getElementById('peran');
    const bidang = document.getElementById('bidang');

    if (!peran || !bidang) {
        console.error('Select #peran atau #bidang tidak ditemukan');
        return;
    }

    function isiBidang() {
        const boleh  = peran.value ? (peta[peran.value] || []) : [];
        const terisi = penuh[peran.value] || [];
        bidang.innerHTML = '';

        const kosong = document.createElement('option');
        kosong.value = '';
        kosong.textContent = peran.value ? 'Pilih Bidang' : 'Pilih peran dulu';
        bidang.appendChild(kosong);

        boleh.forEach(function (b) {
            const o = document.createElement('option');
            const isi = terisi.includes(b);
            o.value = b;
            o.textContent = isi ? b + ' (sudah terisi)' : b;
            o.disabled = isi;
            bidang.appendChild(o);
        });

        if (boleh.length === 1) bidang.value = boleh[0];
        else if (lama && boleh.includes(lama) && !terisi.includes(lama)) bidang.value = lama;
    }

    peran.addEventListener('change', isiBidang);
    isiBidang();
})();
</script>
@endsection
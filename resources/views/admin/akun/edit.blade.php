@extends('layouts.admin')

@section('content')
@php
    $err = collect($errors->messages())->map(fn ($m) => $m[0])->all();
    $errBidang = $err['bidang'] ?? collect($err)->first(fn ($v, $k) => str_starts_with($k, 'bidang.'));
    $ikonSalah = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>';
@endphp

<div class="content akun-page">
    <div class="akun-form-card">

        <div class="akun-form-head">
            <div>
                <h1>Ubah Akun</h1>
                <p>Perbarui data akun pengguna.</p>
            </div>
            <span class="akun-chip">Formulir Pengguna</span>
        </div>

        @if ($err)
            <div class="akun-alert-error">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <div>
                    <strong>Terdapat {{ count($err) }} kesalahan pengisian formulir</strong>
                    <span>Silakan periksa dan perbaiki isian data di bawah ini sebelum menyimpan perubahan.</span>
                </div>
            </div>
        @else
            <div class="akun-info-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                Semua bidang bertanda bintang (*) wajib diisi. Peran diubah lewat tombol "Ubah peran" di daftar akun, dan kata sandi lewat "Reset kata sandi".
            </div>
        @endif

        <form action="{{ route('admin.akun.update', $akun->id_pengguna) }}" method="POST" class="akun-form {{ $err ? 'ada-error' : '' }}" novalidate>
            @csrf
            @method('PUT')

            <div class="akun-form-judul">
                <h2><i></i> Informasi Kredensial &amp; Penugasan</h2>
                <span>✳ Wajib diisi</span>
            </div>

            {{-- Nama lengkap --}}
            <div class="akun-field {{ isset($err['nama']) ? 'has-error' : '' }}">
                <label for="nama">Nama Lengkap <em>*</em></label>
                <div class="akun-input">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M15 9h3M15 13h3M6 17c.5-2 5.5-2 6 0"/></svg>
                    <input type="text" id="nama" name="nama" placeholder="Masukkan nama lengkap" value="{{ old('nama', $akun->nama_lengkap) }}">
                </div>
                @isset($err['nama'])
                    <p class="akun-error">{!! $ikonSalah !!} {{ $err['nama'] }}</p>
                @endisset
            </div>

            {{-- Nama pengguna --}}
            <div class="akun-field {{ isset($err['username']) ? 'has-error' : '' }}">
                <label for="username">Nama Pengguna <em>*</em></label>
                <div class="akun-input">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/></svg>
                    <input type="text" id="username" name="username" placeholder="Masukkan nama pengguna unik" value="{{ old('username', $akun->nama_pengguna) }}">
                    @isset($err['username'])
                        <svg class="status" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                    @endisset
                </div>
                @isset($err['username'])
                    <p class="akun-error">{!! $ikonSalah !!} {{ $err['username'] }}</p>
                @endisset
            </div>

            {{-- Peran (hanya tampil) + Bidang --}}
            <div class="akun-baris">
                <div class="akun-field">
                    <label for="peran">Peran</label>
                    <div class="akun-input">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><circle cx="12" cy="10" r="2"/></svg>
                        <input type="text" id="peran" value="{{ $akun->nama_peran }}" disabled>
                    </div>
                    <p class="akun-hint">Peran tidak bisa diubah di sini.</p>
                </div>

                @if (count($daftarBidang))
                    @php
                        $terpilih = array_values((array) old('bidang', $bidangTerpilih));
                        if (!$terpilih) $terpilih = [''];
                        if (!$multi) $terpilih = array_slice($terpilih, 0, 1);
                    @endphp
                    <div class="akun-field {{ $errBidang ? 'has-error' : '' }}">
                        <label>Bidang <em>*</em></label>
                        <div id="daftarBidang">
                            @foreach ($terpilih as $i => $pilih)
                                <div class="akun-input" style="margin-bottom:8px">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="12" height="18" rx="1"/><path d="M16 9h4v12h-4M8 7h4M8 11h4M8 15h4"/></svg>
                                    <select name="bidang[]">
                                        <option value="">Pilih Bidang</option>
                                        @foreach ($daftarBidang as $b)
                                            <option value="{{ $b }}" @selected($pilih === $b)>{{ $b }}</option>
                                        @endforeach
                                    </select>
                                    @if ($multi && $i > 0)
                                        <button type="button" class="hapus-bidang" aria-label="Hapus bidang"
                                            style="position:absolute; top:0; right:0; width:46px; height:46px; color:#b42318;">✕</button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if ($multi)
                            <button type="button" id="tambahBidang" class="akun-btn-batal" style="margin-top:8px">+ Tambah bidang</button>
                        @endif
                        @if ($errBidang)
                            <p class="akun-error">{!! $ikonSalah !!} {{ $errBidang }}</p>
                        @endif
                    </div>
                @else
                    <div class="akun-field">
                        <label for="bidang">Bidang</label>
                        <div class="akun-input">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="12" height="18" rx="1"/><path d="M16 9h4v12h-4M8 7h4M8 11h4M8 15h4"/></svg>
                            <input type="text" id="bidang" value="Tidak memerlukan bidang" disabled>
                        </div>
                        <p class="akun-hint">Peran {{ $akun->nama_peran }} tidak terikat bidang.</p>
                    </div>
                @endif
            </div>

            <div class="akun-form-aksi">
                <a href="{{ route('admin.akun.index') }}" class="akun-btn-batal">← Batal</a>
                <button type="submit" class="akun-btn-simpan">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

@if ($multi && count($daftarBidang))
<script>
(function () {
    const daftar = document.getElementById('daftarBidang');
    const tambah = document.getElementById('tambahBidang');
    if (!daftar || !tambah) return;

    function pasangHapus(btn) {
        btn.addEventListener('click', function () {
            if (daftar.children.length > 1) btn.closest('.akun-input').remove();
        });
    }
    daftar.querySelectorAll('.hapus-bidang').forEach(pasangHapus);

    tambah.addEventListener('click', function () {
        const klon = daftar.firstElementChild.cloneNode(true);
        klon.querySelector('select').value = '';
        const lama = klon.querySelector('.hapus-bidang');
        if (lama) lama.remove();
        const x = document.createElement('button');
        x.type = 'button';
        x.className = 'hapus-bidang';
        x.setAttribute('aria-label', 'Hapus bidang');
        x.textContent = '✕';
        x.style.cssText = 'position:absolute; top:0; right:0; width:46px; height:46px; color:#b42318;';
        klon.appendChild(x);
        pasangHapus(x);
        daftar.appendChild(klon);
    });
})();
</script>
@endif
@endsection
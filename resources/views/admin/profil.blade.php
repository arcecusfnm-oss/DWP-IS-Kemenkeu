@extends('layouts.admin')

@section('title', 'Profil Admin')

@section('content')
@php
    // ===== DATA SEMENTARA, nanti diganti dari controller / auth()->user() =====
    $adminNama = $adminNama ?? 'Admin Utama';

    // ===== PREVIEW FRONT END (hapus kalau backend sudah jalan) =====
    // /admin/profil?state=sukses  -> tampilan berhasil ganti kata sandi
    // /admin/profil?state=error   -> tampilan kata sandi salah
    $demo = request('state');

    $pesanSukses = session('success') ?? ($demo === 'sukses' ? 'Kata sandi berhasil diubah.' : null);

    $errLama = $errors->first('kata_sandi_lama') ?: ($demo === 'error' ? 'Kata sandi lama salah' : '');
    $errBaru = $errors->first('kata_sandi_baru') ?: ($demo === 'error' ? 'Kata sandi baru minimal 8 karakter' : '');
    // ================================================================
@endphp

<div class="content">
    <section class="profil-card">

        {{-- Kiri: avatar + nama --}}
        <div class="profil-id">
            <span class="profil-avatar" aria-hidden="true">
                <svg viewBox="0 0 160 160" width="160" height="160" fill="currentColor">
                    <defs><clipPath id="profilClip"><circle cx="80" cy="80" r="70" /></clipPath></defs>
                    <circle cx="80" cy="80" r="75" fill="none" stroke="currentColor" stroke-width="10" />
                    <circle cx="80" cy="59" r="30" />
                    <path clip-path="url(#profilClip)" d="M20 160c0-34 26-58 60-58s60 24 60 58z" />
                </svg>
            </span>
            <h1 class="profil-nama">{{ $adminNama }}</h1>
        </div>

        {{-- Kanan: form ganti kata sandi --}}
        <div class="profil-form-wrap">

            @if ($pesanSukses)
                <div class="alert-sukses" role="status" id="alertSukses">
                    <svg class="alert-ikon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="9.5" /><path d="m7.5 12.5 3 3 6-6.5" />
                    </svg>
                    <div class="alert-teks">
                        <strong>{{ $pesanSukses }}</strong>
                        <span>Gunakan kata sandi baru saat masuk berikutnya.</span>
                    </div>
                    <button type="button" class="alert-tutup" id="alertTutup" aria-label="Tutup pesan">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                    </button>
                </div>
            @endif

                <form method="POST" action="{{ route('password.update') }}"
                  class="profil-form {{ ($errLama || $errBaru) ? 'ada-error' : '' }}" id="formSandi" novalidate>
                @csrf
                

                {{-- Kata sandi lama --}}
                <div class="isian {{ $errLama ? 'has-error' : '' }}" data-field="lama">
                    <label for="kata_sandi_lama">Kata Sandi Lama <span class="req" aria-hidden="true">*</span></label>
                    <div class="isian-sandi">
                        <input id="kata_sandi_lama" name="kata_sandi_lama" type="password"
                               placeholder="Masukkan kata sandi lama Anda" autocomplete="current-password"
                               maxlength="100" required
                               value="{{ $demo === 'error' ? 'xxxxxxxxxxxx' : '' }}"
                               aria-describedby="err-lama" aria-invalid="{{ $errLama ? 'true' : 'false' }}">
                        <button type="button" class="tombol-lihat" data-target="kata_sandi_lama" aria-label="Tampilkan kata sandi">
                            @include('admin.partials.ikon-mata')
                        </button>
                    </div>
                    <p class="pesan-error" id="err-lama" @if(!$errLama) hidden @endif>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M12 7v6M12 16.5h.01" /></svg>
                        <span>{{ $errLama }}</span>
                    </p>
                </div>

                {{-- Kata sandi baru --}}
                <div class="isian {{ $errBaru ? 'has-error' : '' }}" data-field="baru">
                    <label for="kata_sandi_baru">Kata Sandi Baru <span class="req" aria-hidden="true">*</span></label>
                    <div class="isian-sandi">
                        <input id="kata_sandi_baru" name="kata_sandi_baru" type="password"
                               placeholder="Masukkan kata sandi baru" autocomplete="new-password"
                               maxlength="100" required minlength="8"
                               value="{{ $demo === 'error' ? 'xxxxx' : '' }}"
                               aria-describedby="err-baru hint-baru" aria-invalid="{{ $errBaru ? 'true' : 'false' }}">
                        <button type="button" class="tombol-lihat" data-target="kata_sandi_baru" aria-label="Tampilkan kata sandi">
                            @include('admin.partials.ikon-mata')
                        </button>
                    </div>
                    <p class="pesan-error" id="err-baru" @if(!$errBaru) hidden @endif>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M12 7v6M12 16.5h.01" /></svg>
                        <span>{{ $errBaru }}</span>
                    </p>
                    <p class="hint" id="hint-baru" @if($errBaru) hidden @endif>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M12 11v5.5M12 7.5h.01" /></svg>
                        Minimal 8 karakter.
                    </p>
                </div>

                <div class="profil-aksi">
                    <button type="reset" class="btn-batal" id="tombolBatal">Batal</button>
                    <button type="submit" class="btn-simpan">Simpan</button>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
(function () {
    const form = document.getElementById('formSandi');
    if (!form) return;

    // Tampilkan / sembunyikan kata sandi
    form.querySelectorAll('.tombol-lihat').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = document.getElementById(btn.dataset.target);
            const tampil = input.type === 'password';
            input.type = tampil ? 'text' : 'password';
            btn.classList.toggle('terlihat', tampil);
            btn.setAttribute('aria-label', tampil ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        });
    });

    function setError(field, pesan) {
        const wrap = form.querySelector('[data-field="' + field + '"]');
        const err  = wrap.querySelector('.pesan-error');
        const hint = wrap.querySelector('.hint');
        const input = wrap.querySelector('input');
        wrap.classList.toggle('has-error', !!pesan);
        input.setAttribute('aria-invalid', pesan ? 'true' : 'false');
        err.hidden = !pesan;
        err.querySelector('span').textContent = pesan || '';
        if (hint) hint.hidden = !!pesan;
        form.classList.toggle('ada-error', !!form.querySelector('.has-error'));
    }

    // Validasi sisi klien (sama dengan teks di desain)
    form.addEventListener('submit', function (e) {
        const lama = form.kata_sandi_lama.value;
        const baru = form.kata_sandi_baru.value;
        let valid = true;

        if (!lama) { setError('lama', 'Kata sandi lama wajib diisi'); valid = false; }
        else setError('lama', '');

        if (baru.length < 8) { setError('baru', 'Kata sandi baru minimal 8 karakter'); valid = false; }
        else setError('baru', '');

        if (!valid) e.preventDefault();
    });

    // Hapus error saat user mengetik lagi
    form.querySelectorAll('input').forEach(function (input) {
        input.addEventListener('input', function () {
            const field = input.closest('.isian').dataset.field;
            if (input.closest('.isian').classList.contains('has-error')) setError(field, '');
        });
    });

    // Batal = kosongkan form + bersihkan error & pesan sukses
    document.getElementById('tombolBatal').addEventListener('click', function () {
        setError('lama', '');
        setError('baru', '');
        const a = document.getElementById('alertSukses');
        if (a) a.remove();
    });

    // Tutup pesan sukses
    const tutup = document.getElementById('alertTutup');
    if (tutup) tutup.addEventListener('click', function () {
        document.getElementById('alertSukses').remove();
    });
})();
</script>
@endsection
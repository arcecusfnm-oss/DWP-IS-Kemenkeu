<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ganti Kata Sandi - DWP-IS Kemenkeu</title>
    <style>
        :root { --coklat:#5b1f0e; --oranye:#c0643a; --krem:#fdf3ee; --garis:#e0cfc5; --merah:#c0392b; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px;
               font-family:system-ui,-apple-system,"Segoe UI",sans-serif; background:var(--krem); color:var(--coklat); }
        .kartu { width:100%; max-width:440px; background:#fff; border-radius:16px; padding:32px;
                 border-top:4px solid var(--oranye); box-shadow:0 20px 50px rgba(91,31,14,.12); }
        h1 { margin:0 0 8px; font-size:22px; }
        .intro { margin:0 0 24px; font-size:14px; line-height:1.5; color:#7a5c52; }
        label { display:block; margin-bottom:6px; font-size:14px; font-weight:600; }
        label em { color:var(--merah); font-style:normal; }
        .isian { margin-bottom:18px; }
        .kolom { position:relative; }
        .kolom input { width:100%; height:46px; padding:0 46px 0 14px; border:1px solid var(--garis);
                       border-radius:10px; font-size:14px; color:var(--coklat); }
        .kolom input:focus { outline:2px solid rgba(192,100,58,.35); border-color:var(--oranye); }
        .kolom input::-ms-reveal, .kolom input::-ms-clear { display:none; }
        .mata { position:absolute; top:0; right:0; width:46px; height:46px; display:grid; place-items:center;
                border:0; background:none; cursor:pointer; color:#7a5c52; }
        .ada-error input { border-color:var(--merah); background:#fff6f4; }
        .pesan { margin:6px 0 0; font-size:12.5px; color:var(--merah); }
        .hint { margin:6px 0 0; font-size:12.5px; color:#7a5c52; }
        .aturan { list-style:none; margin:8px 0 0; padding:0; font-size:12.5px; color:#7a5c52; }
        .aturan li { margin-top:3px; }
        .aturan li.ok { color:#1e8449; }
        .aksi { display:flex; gap:10px; justify-content:flex-end; margin-top:24px; }
        .aksi button { padding:10px 22px; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; border:1px solid transparent; }
        .simpan { background:var(--oranye); color:#fff; }
        .keluar { background:#fff; border-color:var(--garis) !important; color:var(--coklat); }
    </style>
</head>
<body>
    <main class="kartu">
        <h1>Ganti Kata Sandi</h1>
        <p class="intro">Anda harus mengganti kata sandi sementara sebelum melanjutkan.</p>

        @php
            $errLama = $errors->first('kata_sandi_lama');
            $errBaru = $errors->first('kata_sandi_baru');
        @endphp

        <form method="POST" action="{{ route('password.update') }}" novalidate>
            @csrf

            <div class="isian {{ $errLama ? 'ada-error' : '' }}">
                <label for="kata_sandi_lama">Kata Sandi Sementara <em>*</em></label>
                <div class="kolom">
                    <input id="kata_sandi_lama" name="kata_sandi_lama" type="password"
                           placeholder="Masukkan kata sandi sementara" autocomplete="current-password" maxlength="100" required>
                    <button type="button" class="mata" data-target="kata_sandi_lama" aria-label="Tampilkan kata sandi">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                @if ($errLama) <p class="pesan">{{ $errLama }}</p> @endif
            </div>

            <div class="isian {{ $errBaru ? 'ada-error' : '' }}">
                <label for="kata_sandi_baru">Kata Sandi Baru <em>*</em></label>
                <div class="kolom">
                    <input id="kata_sandi_baru" name="kata_sandi_baru" type="password"
                           placeholder="Masukkan kata sandi baru" autocomplete="new-password" maxlength="100" required minlength="8">
                    <button type="button" class="mata" data-target="kata_sandi_baru" aria-label="Tampilkan kata sandi">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                @if ($errBaru)
                    <p class="pesan">{{ $errBaru }}</p>
                @endif
                <ul class="aturan" id="aturan-kata-sandi">
                    <li data-aturan="panjang">○ Minimal 8 karakter</li>
                    <li data-aturan="besar">○ Huruf besar (A-Z)</li>
                    <li data-aturan="kecil">○ Huruf kecil (a-z)</li>
                    <li data-aturan="angka">○ Angka (0-9)</li>
                    <li data-aturan="khusus">○ Karakter khusus (misalnya ! @ # $ % ^ & *)</li>
                </ul>
            </div>

            <div class="aksi">
                <button type="submit" class="simpan">Simpan</button>
            </div>
        </form>

        @if (Route::has('logout'))
            <form method="POST" action="{{ route('logout') }}" class="aksi" style="margin-top:12px;">
                @csrf
                <button type="submit" class="keluar">Keluar</button>
            </form>
        @endif
    </main>

    <script>
        document.querySelectorAll('.mata').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const input = document.getElementById(btn.dataset.target);
                const tampil = input.type === 'password';
                input.type = tampil ? 'text' : 'password';
                btn.setAttribute('aria-label', tampil ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            });
        });

        const inputBaru = document.getElementById('kata_sandi_baru');
        const cek = {
            panjang: v => v.length >= 8,
            besar:   v => /\p{Lu}/u.test(v),
            kecil:   v => /\p{Ll}/u.test(v),
            angka:   v => /[0-9]/.test(v),
            khusus:  v => /[^\p{L}\p{N}\s]/u.test(v),
        };
        inputBaru.addEventListener('input', function () {
            document.querySelectorAll('#aturan-kata-sandi li').forEach(function (li) {
                const ok = cek[li.dataset.aturan](inputBaru.value);
                li.textContent = li.textContent.replace(/^[○✓]/, ok ? '✓' : '○');
                li.classList.toggle('ok', ok);
            });
        });
    </script>
</body>
</html>
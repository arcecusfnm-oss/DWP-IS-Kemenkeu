// Validasi sederhana di browser untuk halaman masuk (T-11).
// Validasi utama tetap dilakukan di server (T-10); ini hanya agar pengguna langsung tahu kolom yang kosong.
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form-masuk');
    const username = document.getElementById('username');
    const password = document.getElementById('password');
    const tombolLihat = document.getElementById('tombol-lihat');

    function tampilkanError(input, pesan) {
        const tempatError = document.getElementById(input.id + '-error');
        tempatError.textContent = pesan;
        input.classList.toggle('tidak-valid', pesan !== '');
    }

    form.addEventListener('submit', function (event) {
        let adaKosong = false;

        if (username.value.trim() === '') {
            tampilkanError(username, 'Nama pengguna wajib diisi');
            adaKosong = true;
        } else {
            tampilkanError(username, '');
        }

        if (password.value === '') {
            tampilkanError(password, 'Kata sandi wajib diisi');
            adaKosong = true;
        } else {
            tampilkanError(password, '');
        }

        if (adaKosong) {
            event.preventDefault();
        }
    });

    // Hapus pesan error saat pengguna mulai mengetik lagi
    [username, password].forEach(function (input) {
        input.addEventListener('input', function () {
            if (input.value !== '') {
                tampilkanError(input, '');
            }
        });
    });

    // Tombol lihat/sembunyikan kata sandi
    tombolLihat.addEventListener('click', function () {
        const tersembunyi = password.type === 'password';
        password.type = tersembunyi ? 'text' : 'password';
        tombolLihat.setAttribute('aria-label', tersembunyi ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    });

    // Countdown lockout login berdasarkan waktu dari server.
    const pesanLockout = document.getElementById('pesan-lockout');

    if (pesanLockout) {
        const countdown = document.getElementById('lockout-countdown');
        const tombolMasuk = form.querySelector('button[type="submit"]');
        const lockedUntil = Number(pesanLockout.dataset.lockoutUntil);

        function updateCountdown() {
            const waktuSekarang = Math.floor(Date.now() / 1000);
            const sisaDetik = Math.max(0, lockedUntil - waktuSekarang);

            const menit = Math.floor(sisaDetik / 60);
            const detik = sisaDetik % 60;

            countdown.textContent =
                String(menit).padStart(2, '0') + ':' +
                String(detik).padStart(2, '0');

            if (sisaDetik <= 0) {
                tombolMasuk.disabled = false;
                pesanLockout.remove();
                return;
            }

            tombolMasuk.disabled = true;
            setTimeout(updateCountdown, 1000);
        }

        updateCountdown();
    }
});

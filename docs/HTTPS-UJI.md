\# Lingkungan Uji HTTPS (Lokal)

\## Tujuan
\Menjalankan aplikasi lewat HTTPS di laptop masing-masing untuk pengujian.

\## Langkah
\1. Pastikan proyek ada di folder Herd dan situs terdaftar di Herd.
\2. Buka aplikasi Herd, tab Sites, pilih situs DWP-IS-Kemenkeu.
\3. Klik ikon gembok (Secure) sampai URL berubah menjadi https://.
\4. Ubah APP_URL di .env menjadi https://dwp-is-kemenkeu.test
\5. Jalankan: php artisan config:clear
\6. Buka https://dwp-is-kemenkeu.test di browser.

\## Catatan
\- Sertifikat dibuat otomatis oleh Herd dan hanya berlaku di laptop sendiri.
\- Untuk mematikan, klik ikon gembok lagi di Herd.
\- File .env tidak boleh di-commit.
\- Lingkungan ini bersifat sementara dan hanya untuk pengujian lokal.
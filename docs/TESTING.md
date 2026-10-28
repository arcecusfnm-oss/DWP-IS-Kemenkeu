\# Panduan Pengujian



\## Menjalankan test

php artisan test



\## Database uji

\- Nama: dwp\_is\_kemenkeu\_test

\- Dibuat manual (kosong) di MySQL lokal. Tabel dibuat otomatis lewat migration.

\- Dikonfigurasi di phpunit.xml. Host, username, dan password dibaca dari .env.



\## Struktur folder

\- tests/Unit: logika murni tanpa database

\- tests/Feature: tes lewat HTTP dan database

\- Penamaan file: NamaFiturTest.php



\## Fixture (dari migration T-07)

\- 5 peran: Admin, Penasihat, Pimpinan Bidang, Anggota Bidang, Bendahara

\- 5 bidang: Ekonomi, Sosial Budaya, Pendidikan, Sekretariat, Bendahara

\- Akun uji (nama pengguna): admin, penasihat, pimpinan, anggota, bendahara (aktif), nonaktif (status nonaktif)

\- Kata sandi dari SEED_ADMIN_PASSWORD dan SEED_USER_PASSWORD di .env, tidak ditulis di kode


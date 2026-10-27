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



\## Rencana fixture (dibuat setelah migration T-07)

\- 5 peran dan 5 bidang

\- 1 akun Admin aktif, 1 akun nonaktif, 1 akun non-Admin

\- Kata sandi uji dari environment variable, tidak ditulis di kode


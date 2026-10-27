# DWP-IS-Kemenkeu

Sistem Informasi DWP IS Kemenkeu.

## Teknologi

- Laravel 12
- PHP 8.3
- MySQL
- Composer

## Persiapan

1. Pastikan PHP, Composer, dan MySQL sudah terpasang.
2. Clone repositori ini dan masuk ke direktori proyek.
3. Instal dependensi PHP:

   `composer install`

4. Salin `.env.example` menjadi `.env`.
5. Atur koneksi database di `.env`.
6. Buat application key:

   `php artisan key:generate`

7. Jalankan migrasi database:

   `php artisan migrate`

8. Jalankan server pengembangan:

   `php artisan serve`

## Dokumentasi

Dokumentasi arsitektur, kontribusi, dan konfigurasi environment tersedia di direktori `docs/`.

## Kontribusi

Gunakan branch terpisah untuk setiap pekerjaan dan ajukan Pull Request ke branch `main` untuk ditinjau sebelum digabungkan.

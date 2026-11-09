# DWP-IS-Kemenkeu

Sistem Informasi Dharma Wanita Persatuan Kementerian Keuangan (DWP Kemenkeu).
Aplikasi ini mendukung pengelolaan akun pengguna, peran, bidang organisasi, serta akses pengguna ke dalam sistem.

---

## Teknologi
- **Framework:** Laravel 12
- **Language:** PHP 8.3
- **Database:** MySQL
- **Dependency Manager:** Composer

---

## 1. Persyaratan System
Pastikan perangkat Anda telah terpasang:
- PHP >= 8.3
- Composer
- MySQL Database
- Git

---

## 2. Persiapan Proyek

1. **Clone Repositori:**
   ```bash
   git clone https://github.com/arcecusfnm-oss/DWP-IS-Kemenkeu.git
   ```

2. **Masuk ke Direktori Proyek:**
   ```bash
   cd DWP-IS-Kemenkeu
   ```

3. **Instal Dependensi:**
   ```bash
   composer install
   ```

4. **Salin File Environment:**
   - **Windows PowerShell:**
     ```powershell
     Copy-Item .env.example .env
     ```
   - **Linux / macOS:**
     ```bash
     cp .env.example .env
     ```

5. **Generate Application Key:**
   ```bash
   php artisan key:generate
   ```

---

## 3. Konfigurasi Environment

Buka file `.env` dan sesuaikan variabel berikut sesuai dengan konfigurasi lokal Anda:

```env
APP_NAME=DWP-IS-Kemenkeu
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dwp_is_kemenkeu
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false

CACHE_STORE=database
QUEUE_CONNECTION=database

SEED_ADMIN_PASSWORD=
SEED_USER_PASSWORD=
```

> **Catatan Penting:**
> - `SEED_ADMIN_PASSWORD`: Kata sandi untuk akun Admin bawaan seeder.
> - `SEED_USER_PASSWORD`: Kata sandi untuk akun pengguna uji lainnya.
> - Pastikan kedua variabel seeder di atas terisi sebelum menjalankan proses *seeding*.
> - Jangan pernah melakukan commit file `.env` yang berisi kredensial asli ke versi kontrol. Detail lengkap terdapat pada `docs/ENVIRONMENT.md`.

---

## 4. Migration & Seeder

Struktur database disiapkan menggunakan fitur migrasi Laravel.

1. **Menjalankan Migration:**
   ```bash
   php artisan migrate
   ```

2. **Menjalankan Migration beserta Data Awal (Seeder):**
   ```bash
   php artisan migrate --seed
   ```

### Data Awal yang Dihasilkan Seeder:
- **Peran:** Admin, Penanggung Jawab, Pimpinan Bidang, Anggota Bidang, Bendahara.
- **Bidang:** Ekonomi, Sosial Budaya, Pendidikan, Sekretariat, Bendahara.
- **Akun Uji Bawaan:**

| Nama Pengguna | Peran | Status |
| :--- | :--- | :--- |
| `admin` | Admin | Aktif |
| `penanggungjawab` | Penanggung Jawab | Aktif |
| `pimpinan` | Pimpinan Bidang | Aktif |
| `anggota` | Anggota Bidang | Aktif |
| `bendahara` | Bendahara | Aktif |
| `nonaktif` | Anggota Bidang | Nonaktif |

*Seluruh kata sandi akun uji disimpan dalam bentuk hash menggunakan algoritma BCrypt.*

---

## 5. Menjalankan Aplikasi

1. **Jalankan Server Lokal:**
   ```bash
   php artisan serve
   ```
2. **Akses Aplikasi:**
   Buka browser dan kunjungi `http://127.0.0.1:8000`.

*(Untuk pengujian lokal menggunakan protokol HTTPS, silakan merujuk ke panduan `docs/HTTPS-UJI.md`).*

---

## 6. Daftar Endpoint Sprint 1

### Akses Pengguna & Otentikasi
| Metode | Alamat | Fungsi |
| :--- | :--- | :--- |
| `GET` | `/` | Halaman utama |
| `GET` | `/login` | Menampilkan formulir login |
| `POST` | `/login` | Memproses sesi login |
| `POST` | `/logout` | Memproses pengakhiran sesi (logout) |
| `POST` | `/ubah-kata-sandi` | Mengubah kata sandi pengguna aktif |

### Dashboard & Kelola Bidang
| Metode | Alamat | Fungsi |
| :--- | :--- | :--- |
| `GET` | `/admin/dashboard` | Dashboard Admin |
| `GET` | `/admin/profil` | Menampilkan profil Admin |
| `GET` | `/admin/bidang` | Daftar bidang organisasi |
| `GET` | `/admin/bidang/tambah` | Formulir tambah bidang |
| `POST` | `/admin/bidang` | Menyimpan bidang baru |
| `GET` | `/admin/bidang/{id}/edit` | Formulir edit bidang |
| `DELETE` | `/admin/bidang/{id}` | Menghapus bidang |

### Pengelolaan Akun (RBAC Admin)
| Metode | Alamat | Fungsi |
| :--- | :--- | :--- |
| `GET` | `/admin/akun` | Menampilkan daftar akun |
| `GET` | `/admin/akun/tambah` | Menampilkan formulir pembuatan akun |
| `POST` | `/admin/akun` | Membuat akun pengguna baru |
| `GET` | `/admin/akun/{id}/edit` | Menampilkan formulir edit akun |
| `PUT` | `/admin/akun/{id}` | Memperbarui data pengguna |
| `PUT` | `/admin/akun/{id}/bidang` | Menetapkan/mengubah bidang akun |
| `GET` | `/admin/akun/{id}/peran` | Menampilkan konfigurasi peran akun |
| `PUT` | `/admin/akun/{id}/peran` | Memperbarui peran (*role*) akun |
| `POST` | `/admin/akun/{id}/reset-password` | Mengatur ulang kata sandi pengguna oleh Admin |
| `DELETE` | `/admin/akun/{id}` | Menghapus akun (Soft Delete) |

> Untuk melihat daftar rute lengkap dalam aplikasi, jalankan: `php artisan route:list`.

---

## 7. Pengujian (Testing)

Proyek ini dilengkapi dengan pengujian otomatis (*Feature & Unit Testing*).

Untuk menjalankan seluruh suite pengujian:
```bash
php artisan test
```
Detail lengkap mengenai struktur pengujian dapat dilihat pada `docs/TESTING.md`.

---

## 8. Dokumentasi Tambahan

Dokumen teknis pendukung yang tersedia pada direktori `docs/`:

- `ARCHITECTURE.md` - Arsitektur sistem & alur data.
- `CONTRIBUTING.md` - Panduan standar kontribusi & alur Git.
- `ENVIRONMENT.md` - Panduan detail variabel lingkungan.
- `HTTPS-UJI.md` - Panduan setup HTTPS di lingkungan lokal.
- `TESTING.md` - Panduan penulisan & eksekusi pengujian.

---

## 9. Panduan Kontribusi

1. Buat branch baru untuk setiap pengerjaan fitur/perbaikan (`feat/nama-fitur` atau `fix/nama-perbaikan`).
2. Pastikan seluruh pengujian (`php artisan test`) berhasil sebelum membuat Pull Request.
3. Ajukan Pull Request ke branch `main` untuk dilakukan peninjauan kode (*code review*).

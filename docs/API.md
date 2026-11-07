# Dokumentasi HTTP Endpoint DWP-IS-Kemenkeu

## 1. Pendahuluan

Dokumen ini menjelaskan HTTP endpoint yang digunakan dalam aplikasi DWP-IS-Kemenkeu pada Sprint 1.

Aplikasi menggunakan Laravel Web Routes. Endpoint memproses permintaan melalui browser dan umumnya mengembalikan halaman Blade, redirect, atau pesan validasi. Endpoint yang didokumentasikan bukan REST API JSON.

## 2. Informasi Umum

| Komponen | Keterangan |
|---|---|
| Backend | Laravel 12 |
| Database | MySQL |
| Definisi route | `routes/web.php` |
| Autentikasi | Laravel Authentication dan session |
| Format interaksi | HTTP request melalui browser dan form |
| Otorisasi Admin | Middleware `role:Admin` |

## 3. Autentikasi dan Hak Akses

| Jenis akses | Keterangan |
|---|---|
| Publik | Dapat diakses tanpa login |
| Guest | Hanya untuk pengguna yang belum login |
| Auth | Hanya untuk pengguna yang sudah login |
| Admin | Harus login dan memiliki peran Admin |
| NoCache | Mencegah halaman tertentu ditampilkan dari cache browser |

Endpoint pengelolaan akun, bidang, dashboard, dan profil Admin dilindungi middleware autentikasi, otorisasi Admin, dan NoCache.

Pengguna yang sudah login dapat mengganti kata sandinya sendiri.

## 4. Daftar HTTP Endpoint

### 4.1 Halaman Publik dan Autentikasi

| Method | Endpoint | Route Name | Fungsi | Akses |
|---|---|---|---|---|
| GET | `/` | `beranda` | Menampilkan halaman utama | Publik |
| GET | `/login` | `login` | Menampilkan halaman login | Guest |
| POST | `/login` | `login.process` | Memproses autentikasi pengguna | Guest |
| POST | `/logout` | `logout` | Mengakhiri sesi pengguna | Auth |

### 4.2 Pengelolaan Kata Sandi

| Method | Endpoint | Route Name | Fungsi | Akses |
|---|---|---|---|---|
| POST | `/ubah-kata-sandi` | `password.update` | Mengubah kata sandi akun sendiri | Auth |

### 4.3 Dashboard dan Profil Admin

| Method | Endpoint | Route Name | Fungsi | Akses |
|---|---|---|---|---|
| GET | `/admin/dashboard` | `admin.dashboard` | Menampilkan dashboard Admin | Admin |
| GET | `/admin/profil` | `admin.profil` | Menampilkan profil Admin | Admin |

### 4.4 Pengelolaan Bidang

| Method | Endpoint | Route Name | Fungsi | Akses |
|---|---|---|---|---|
| GET | `/admin/bidang` | `admin.bidang.index` | Menampilkan daftar bidang | Admin |
| GET | `/admin/bidang/tambah` | `admin.bidang.create` | Menampilkan formulir tambah bidang | Admin |
| POST | `/admin/bidang` | `admin.bidang.store` | Menyimpan bidang baru | Admin |
| GET | `/admin/bidang/{id}/edit` | `admin.bidang.edit` | Menampilkan formulir edit bidang | Admin |
| DELETE | `/admin/bidang/{id}` | `admin.bidang.destroy` | Menghapus bidang | Admin |

### 4.5 Pengelolaan Akun

| Method | Endpoint | Route Name | Fungsi | Akses |
|---|---|---|---|---|
| GET | `/admin/akun` | `admin.akun.index` | Menampilkan daftar akun | Admin |
| GET | `/admin/akun/tambah` | `admin.akun.create` | Menampilkan formulir tambah akun | Admin |
| POST | `/admin/akun` | `admin.akun.store` | Membuat akun baru | Admin |
| GET | `/admin/akun/{id}/edit` | `admin.akun.edit` | Menampilkan formulir edit akun | Admin |
| PUT | `/admin/akun/{id}` | `admin.akun.update` | Memperbarui data akun | Admin |
| PUT | `/admin/akun/{id}/bidang` | `admin.akun.bidang.update` | Memperbarui bidang akun | Admin |
| DELETE | `/admin/akun/{id}` | `admin.akun.destroy` | Menghapus akun | Admin |
| GET | `/admin/akun/{id}/peran` | `admin.akun.peran.edit` | Menampilkan formulir perubahan peran | Admin |
| PUT | `/admin/akun/{id}/peran` | `admin.akun.peran.update` | Memperbarui peran akun | Admin |
| POST | `/admin/akun/{id}/reset-password` | `admin.akun.reset-password` | Mengatur ulang kata sandi akun | Admin |

Keterangan: `{id}` merupakan ID akun atau bidang sesuai endpoint. Route pengelolaan akun membatasi parameter ID berupa angka.

## 5. Detail Request dan Validasi

### 5.1 Login

**Endpoint:** `POST /login`

| Parameter | Tipe | Ketentuan |
|---|---|---|
| `username` | String | Wajib diisi, maksimal 50 karakter |
| `password` | String | Wajib diisi, maksimal 100 karakter |

Perilaku:
- Username dan kata sandi diperiksa untuk autentikasi.
- Akun yang tidak aktif tidak dapat login.
- Lima kali percobaan login gagal menyebabkan pembatasan sementara selama 5 menit.
- Login berhasil membuat sesi autentikasi baru.
- Admin diarahkan ke dashboard Admin, sedangkan peran lain diarahkan ke halaman utama.

Pesan kesalahan:
- Nama pengguna atau kata sandi salah.
- Akun tidak aktif. Hubungi Admin Sekretariat.
- Terlalu banyak percobaan masuk. Silakan coba lagi dalam 5 menit.

### 5.2 Logout

**Endpoint:** `POST /logout`

Tidak memerlukan parameter tambahan.

Perilaku:
- Mengakhiri autentikasi pengguna.
- Menginvalidasi session.
- Membuat ulang token CSRF.
- Mengarahkan pengguna ke halaman login.

### 5.3 Ubah Kata Sandi

**Endpoint:** `POST /ubah-kata-sandi`

| Parameter | Tipe | Ketentuan |
|---|---|---|
| `kata_sandi_lama` | String | Wajib diisi |
| `kata_sandi_baru` | String | Wajib diisi, minimal 8 karakter |

Perilaku:
- Kata sandi lama harus sesuai dengan akun yang sedang login.
- Pengguna hanya dapat mengubah kata sandi akunnya sendiri.
- Permintaan yang menyertakan `id_pengguna` atau `user_id` ditolak.
- Kata sandi baru disimpan menggunakan mekanisme hashing.

Pesan sukses: Kata sandi berhasil diubah.

### 5.4 Tambah Bidang

**Endpoint:** `POST /admin/bidang`

| Parameter | Tipe | Ketentuan |
|---|---|---|
| `nama_bidang` | String | Wajib diisi, maksimal 255 karakter, harus unik |

Pesan validasi:
- Nama bidang wajib diisi.
- Nama bidang harus berupa teks.
- Nama bidang maksimal 255 karakter.
- Nama bidang tersebut sudah tersedia.

Jika berhasil, sistem menyimpan bidang dan mengarahkan Admin ke daftar bidang dengan pesan sukses.

### 5.5 Tambah Akun

**Endpoint:** `POST /admin/akun`

| Parameter | Tipe | Ketentuan |
|---|---|---|
| `nama` | String | Wajib diisi, maksimal 255 karakter |
| `username` | String | Wajib diisi, maksimal 50 karakter, unik |
| `peran` | String | Wajib diisi dan harus tersedia pada data peran |
| `bidang` | String | Wajib diisi dan harus tersedia pada data bidang |
| `password` | String | Wajib diisi, minimal 8 dan maksimal 100 karakter |

Perilaku:
- Akun baru dibuat dengan status aktif.
- Akun baru ditandai perlu mengganti kata sandi.
- Kata sandi disimpan dalam bentuk hash.
- Setelah berhasil, Admin diarahkan ke daftar akun.

### 5.6 Ubah Data Akun

**Endpoint:** `PUT /admin/akun/{id}`

| Parameter | Tipe | Ketentuan |
|---|---|---|
| `nama` | String | Wajib diisi, maksimal 255 karakter |
| `username` | String | Wajib diisi, maksimal 50 karakter, unik kecuali akun sendiri |
| `bidang` | String | Wajib diisi dan harus tersedia pada data bidang |

Jika akun tidak ditemukan atau sudah dihapus, sistem mengembalikan respons 404.

### 5.7 Ubah Bidang Akun

**Endpoint:** `PUT /admin/akun/{id}/bidang`

| Parameter | Tipe | Ketentuan |
|---|---|---|
| `id_bidang` | Integer | Wajib diisi dan harus tersedia pada tabel bidang |

Jika akun tidak ditemukan atau sudah dihapus, sistem mengembalikan respons 404.

### 5.8 Ubah Peran Akun

**Endpoint:** `PUT /admin/akun/{id}/peran`

| Parameter | Tipe | Ketentuan |
|---|---|---|
| `peran` | String | Wajib diisi dan harus tersedia pada data peran |

Perilaku:
- Admin tidak dapat mengubah peran akunnya sendiri.
- Perubahan ke peran yang sama dengan peran saat ini ditolak.
- Akun yang tidak ditemukan menghasilkan respons 404.
- Permintaan perubahan peran akun sendiri menghasilkan respons 403.

### 5.9 Hapus Akun

**Endpoint:** `DELETE /admin/akun/{id}`

Tidak memerlukan parameter tambahan selain ID pada URL.

Perilaku:
- Admin tidak dapat menghapus akun sendiri.
- Penghapusan dilakukan menggunakan soft delete melalui kolom `dihapus_pada`.
- Akun yang tidak ditemukan atau sudah dihapus menghasilkan respons 404.
- Upaya menghapus akun sendiri menghasilkan respons 403.

### 5.10 Reset Kata Sandi Akun

**Endpoint:** `POST /admin/akun/{id}/reset-password`

Tidak memerlukan parameter tambahan.

Perilaku:
- Sistem membuat kata sandi sementara sepanjang 12 karakter.
- Kata sandi sementara disimpan dalam bentuk hash.
- Akun ditandai perlu mengganti kata sandi.
- Sistem mengarahkan Admin ke daftar akun dan menyampaikan kata sandi sementara melalui flash session.

Kata sandi sementara tidak dicantumkan dalam dokumentasi ini.

## 6. Perilaku Respons

| Kondisi | Perilaku |
|---|---|
| Berhasil menampilkan halaman | Mengembalikan halaman Blade |
| Berhasil menyimpan atau memperbarui data | Redirect ke halaman tujuan dengan pesan flash |
| Validasi gagal | Kembali ke halaman sebelumnya dengan pesan kesalahan validasi |
| Tidak terautentikasi | Ditangani oleh middleware autentikasi |
| Tidak memiliki hak akses | Menghasilkan respons 403 |
| Data tidak ditemukan | Menghasilkan respons 404 |

## 7. Catatan Implementasi

Terdapat ketidaksesuaian pada pengelolaan bidang yang perlu ditindaklanjuti:

- Route `GET /admin/bidang/{id}/edit` sudah terdaftar, tetapi method `edit()` belum ditemukan pada `BidangController`.
- Route `DELETE /admin/bidang/{id}` sudah terdaftar, tetapi method `destroy()` belum ditemukan pada `BidangController`.

Dengan demikian, kedua endpoint tersebut belum dapat dinyatakan berfungsi sampai implementasi controller dilengkapi dan diuji.

## 8. Pengujian

Pengujian aplikasi dapat dijalankan menggunakan perintah:

```bash
php artisan test

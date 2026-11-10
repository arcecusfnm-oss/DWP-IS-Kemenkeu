# Kontrak HTTP Endpoint Sprint 2 DWP-IS-Kemenkeu

**Tugas:** T-21 (lintas PBI B-011 sampai B-048)
**Status:** DRAFT, menunggu persetujuan tim pada Daily Scrum Hari 1.
Tanda `[ASUMSI]` berarti belum dikonfirmasi klien (lihat bagian 7).

## 1. Pendahuluan

Dokumen ini adalah kontrak HTTP endpoint Sprint 2 yang disepakati **sebelum** implementasi, supaya backend dan front-end dapat dikerjakan paralel. Cakupan: pengelolaan akun (lanjutan), anggota, jabatan bidang, surat, program kerja, dan kegiatan.

Seperti Sprint 1, aplikasi memakai Laravel Web Routes. Endpoint memproses permintaan melalui browser dan mengembalikan halaman Blade, redirect, atau pesan validasi. Endpoint yang didokumentasikan **bukan REST API JSON**.

## 2. Informasi Umum

| Komponen              | Keterangan                                                        |
| --------------------- | ----------------------------------------------------------------- |
| Backend               | Laravel 12                                                        |
| Database              | MySQL                                                             |
| Definisi route        | `routes/web.php`                                                  |
| Autentikasi           | Laravel Authentication dan session                                |
| Format interaksi      | HTTP request melalui browser dan form                             |
| Otorisasi             | Middleware `role:...` dan policy                                  |
| Metode PUT dan DELETE | Dikirim dari form HTML memakai method spoofing (`@method('PUT')`) |
| Format ID `{id}`      | Hanya angka (dibatasi pada definisi route)                        |

Aturan tambahan Sprint 2 (DoD): input divalidasi, query memakai ORM atau prepared statement, output di-escape, aktivitas penting dicatat di log tanpa kata sandi atau token.

## 3. Autentikasi dan Hak Akses

### 3.1 Jenis akses

| Jenis akses       | Keterangan                                                                          |
| ----------------- | ----------------------------------------------------------------------------------- |
| Auth              | Pengguna sudah login                                                                |
| Admin             | Login dan peran Admin (`role:Admin`)                                                |
| Pimpinan          | Login dan peran Pimpinan Bidang (`role:Pimpinan Bidang`)                            |
| Pimpinan, Anggota | Peran Pimpinan Bidang atau Anggota Bidang (`role:Pimpinan Bidang,Anggota Bidang`)   |
| Admin, Penasihat  | Peran Admin atau Penasihat (`role:Admin,Penasihat`)                                 |
| Surat             | Admin atau akun bidang Sekretariat (policy/middleware khusus persuratan) `[ASUMSI]` |
| Pemilik           | Pimpinan Bidang yang bidangnya sama dengan bidang program kerja (dicek di server)   |
| NoCache           | Mencegah halaman ditampilkan dari cache browser (dipakai seperti Sprint 1)          |

Semua endpoint di bawah dilindungi middleware autentikasi. Akses lintas peran, lintas bidang, atau tanpa sesi ditolak di server (bukan hanya disembunyikan di tampilan).

### 3.2 Ringkasan akses per peran

- **Admin**: mengelola akun (alih peran, aktifkan, nonaktifkan), mengelola anggota, menetapkan ketua dan wakil ketua bidang, mencatat/mengubah/mencari surat, serta melihat program kerja bidang lain dan seluruh bidang.
- **Penasihat**: hanya melihat program kerja bidang lain dan seluruh bidang `[ASUMSI]`.
- **Pimpinan Bidang**: menambah dan mengubah program kerja milik bidangnya, menambah kegiatan pada program tersebut, melihat daftar anggota bidangnya, serta melihat program bidang sendiri dan bidang lain.
- **Anggota Bidang**: melihat daftar anggota bidangnya, program kerja bidang sendiri, dan program bidang lain.
- **Akun yang bidangnya Sekretariat** (peran apa pun): mencatat, mengubah, dan mencari surat `[ASUMSI]`, serta melihat program bidang lain.

Fitur yang hanya dapat dilakukan satu jenis pengguna: kelola akun, anggota, dan jabatan bidang hanya Admin; tambah dan ubah program kerja serta tambah kegiatan hanya Pimpinan Bidang pemilik program.

## 4. Daftar HTTP Endpoint

### 4.1 Pengelolaan Akun (B-011, B-012, B-013)

| Method | Endpoint                       | Route Name               | Fungsi                                 | Akses |
| ------ | ------------------------------ | ------------------------ | -------------------------------------- | ----- |
| POST   | `/admin/akun/{id}/alih-admin`  | `admin.akun.alih-admin`  | Mengalihkan peran Admin ke akun tujuan | Admin |
| POST   | `/admin/akun/{id}/nonaktifkan` | `admin.akun.nonaktifkan` | Menonaktifkan akun                     | Admin |
| POST   | `/admin/akun/{id}/aktifkan`    | `admin.akun.aktifkan`    | Mengaktifkan kembali akun              | Admin |

### 4.2 Anggota (B-015, B-016, B-020, B-021, B-022)

| Method | Endpoint                     | Route Name                    | Fungsi                                        | Akses             |
| ------ | ---------------------------- | ----------------------------- | --------------------------------------------- | ----------------- |
| GET    | `/admin/anggota`             | `admin.anggota.index`         | Daftar lengkap anggota, filter `?bidang={id}` | Admin             |
| GET    | `/admin/anggota/tambah`      | `admin.anggota.create`        | Formulir tambah anggota                       | Admin             |
| POST   | `/admin/anggota`             | `admin.anggota.store`         | Menyimpan anggota baru                        | Admin             |
| GET    | `/admin/anggota/{id}/edit`   | `admin.anggota.edit`          | Formulir ubah anggota                         | Admin             |
| PUT    | `/admin/anggota/{id}`        | `admin.anggota.update`        | Memperbarui data anggota                      | Admin             |
| PUT    | `/admin/anggota/{id}/bidang` | `admin.anggota.bidang.update` | Memindahkan anggota ke bidang lain            | Admin             |
| GET    | `/anggota-bidang-saya`       | `anggota.bidang-saya`         | Daftar anggota bidang akun yang login         | Pimpinan, Anggota |

### 4.3 Jabatan Bidang (B-017, B-019)

| Method | Endpoint                         | Route Name                        | Fungsi                        | Akses |
| ------ | -------------------------------- | --------------------------------- | ----------------------------- | ----- |
| PUT    | `/admin/bidang/{id}/ketua`       | `admin.bidang.ketua.update`       | Menetapkan ketua bidang       | Admin |
| PUT    | `/admin/bidang/{id}/wakil-ketua` | `admin.bidang.wakil-ketua.update` | Menetapkan wakil ketua bidang | Admin |

### 4.4 Surat (B-035, B-036, B-038, B-039)

| Method | Endpoint               | Route Name            | Fungsi                                      | Akses |
| ------ | ---------------------- | --------------------- | ------------------------------------------- | ----- |
| GET    | `/surat`               | `surat.index`         | Daftar surat, pencarian `?q=&dari=&sampai=` | Surat |
| GET    | `/surat/masuk/tambah`  | `surat.masuk.create`  | Formulir surat masuk                        | Surat |
| POST   | `/surat/masuk`         | `surat.masuk.store`   | Mencatat surat masuk                        | Surat |
| GET    | `/surat/keluar/tambah` | `surat.keluar.create` | Formulir surat keluar                       | Surat |
| POST   | `/surat/keluar`        | `surat.keluar.store`  | Mencatat surat keluar                       | Surat |
| GET    | `/surat/{id}/edit`     | `surat.edit`          | Formulir ubah surat                         | Surat |
| PUT    | `/surat/{id}`          | `surat.update`        | Memperbarui data surat                      | Surat |

### 4.5 Program Kerja (B-041, B-042, B-044, B-045, B-046)

| Method | Endpoint                     | Route Name                  | Fungsi                                        | Akses                       |
| ------ | ---------------------------- | --------------------------- | --------------------------------------------- | --------------------------- |
| GET    | `/program-kerja/tambah`      | `program-kerja.create`      | Formulir tambah program                       | Pimpinan                    |
| POST   | `/program-kerja`             | `program-kerja.store`       | Menyimpan program baru                        | Pimpinan                    |
| GET    | `/program-kerja/bidang-saya` | `program-kerja.bidang-saya` | Program milik bidang sendiri                  | Pimpinan, Anggota           |
| GET    | `/program-kerja/bidang-lain` | `program-kerja.bidang-lain` | Program bidang lain (hanya baca)              | Auth `[ASUMSI]`             |
| GET    | `/admin/program-kerja`       | `admin.program-kerja.index` | Program seluruh bidang, filter `?bidang={id}` | Admin, Penasihat `[ASUMSI]` |
| GET    | `/program-kerja/{id}/edit`   | `program-kerja.edit`        | Formulir ubah program                         | Pimpinan + Pemilik          |
| PUT    | `/program-kerja/{id}`        | `program-kerja.update`      | Memperbarui program                           | Pimpinan + Pemilik          |

### 4.6 Kegiatan (B-048)

| Method | Endpoint                              | Route Name                      | Fungsi                          | Akses              |
| ------ | ------------------------------------- | ------------------------------- | ------------------------------- | ------------------ |
| GET    | `/program-kerja/{id}/kegiatan/tambah` | `program-kerja.kegiatan.create` | Formulir tambah kegiatan        | Pimpinan + Pemilik |
| POST   | `/program-kerja/{id}/kegiatan`        | `program-kerja.kegiatan.store`  | Menyimpan kegiatan pada program | Pimpinan + Pemilik |

## 5. Detail Request dan Validasi

### 5.1 Alih Admin (B-011)

**Endpoint:** `POST /admin/akun/{id}/alih-admin` (`{id}` = akun tujuan)

Tidak memerlukan parameter tambahan selain konfirmasi dari dialog.

Perilaku:

- Akun tujuan harus ada dan berstatus aktif.
- Admin tidak dapat mengalihkan ke akunnya sendiri (respons 403).
- Akun tujuan menjadi Admin dan akun asal turun menjadi Anggota Bidang `[ASUMSI]` dalam satu transaksi; sistem selalu memiliki tepat satu Admin. Bila penyimpanan gagal, kedua peran kembali seperti semula.
- Pengalihan dicatat di log (waktu, Admin asal, akun tujuan).

Pesan kesalahan: Akun tujuan tidak aktif. / Tidak dapat mengalihkan peran ke akun sendiri. / Akun tidak ditemukan (404).

### 5.2 Nonaktifkan dan Aktifkan Akun (B-012, B-013)

**Endpoint:** `POST /admin/akun/{id}/nonaktifkan` dan `POST /admin/akun/{id}/aktifkan`

Tidak memerlukan parameter tambahan.

Perilaku nonaktifkan:

- Status akun menjadi `nonaktif`, data akun tetap tersimpan.
- Sesi aktif akun tersebut dicabut; akun tidak dapat login ("Akun tidak aktif").
- Admin tidak dapat menonaktifkan akunnya sendiri (respons 403).

Perilaku aktifkan:

- Status akun menjadi `aktif`; peran dan bidang tidak berubah.
- Akun yang sudah aktif ditolak dengan pesan jelas tanpa error server.
- Akun tidak ditemukan menghasilkan respons 404.

Kedua aksi dicatat di log tanpa data sensitif.

### 5.3 Tambah Anggota (B-016)

**Endpoint:** `POST /admin/anggota`

| Parameter       | Tipe    | Ketentuan                                              |
| --------------- | ------- | ------------------------------------------------------ |
| `nama`          | String  | Wajib diisi, maksimal 255 karakter                     |
| `nomor_telepon` | String  | Wajib diisi, hanya angka, 8 sampai 15 digit `[ASUMSI]` |
| `id_bidang`     | Integer | Wajib diisi dan harus tersedia pada tabel bidang       |

Perilaku:

- Anggota baru disimpan dengan status aktif dan langsung tampil pada daftar anggota.
- Pembuatan dicatat di log (waktu, Admin pelaku, nama anggota).
- Setelah berhasil, Admin diarahkan ke daftar anggota dengan pesan sukses.

Pesan validasi: Nama wajib diisi. / Nomor telepon wajib diisi. / Nomor telepon harus 8 sampai 15 digit angka. / Bidang tidak tersedia.

### 5.4 Ubah Data Anggota (B-021)

**Endpoint:** `PUT /admin/anggota/{id}`

| Parameter       | Tipe   | Ketentuan                                              |
| --------------- | ------ | ------------------------------------------------------ |
| `nama`          | String | Wajib diisi, maksimal 255 karakter                     |
| `nomor_telepon` | String | Wajib diisi, hanya angka, 8 sampai 15 digit `[ASUMSI]` |

Perilaku: validasi gagal mengembalikan ke form dan data tidak berubah. Anggota tidak ditemukan menghasilkan respons 404. Perubahan dicatat di log.

### 5.5 Pindah Bidang Anggota (B-015)

**Endpoint:** `PUT /admin/anggota/{id}/bidang`

| Parameter   | Tipe    | Ketentuan                                        |
| ----------- | ------- | ------------------------------------------------ |
| `id_bidang` | Integer | Wajib diisi dan harus tersedia pada tabel bidang |

Perilaku:

- Setelah pindah, anggota hanya memiliki satu bidang aktif.
- Pindah ke bidang yang sama dengan bidang saat ini ditolak dengan pesan umum.
- Jabatan ketua atau wakil ketua anggota pada bidang lama dilepas `[ASUMSI]`.
- Pemindahan dicatat di log (bidang asal dan tujuan).

### 5.6 Daftar Anggota (B-020, B-022)

**Endpoint:** `GET /admin/anggota` dan `GET /anggota-bidang-saya`

| Parameter | Tipe    | Ketentuan                                                               |
| --------- | ------- | ----------------------------------------------------------------------- |
| `bidang`  | Integer | Opsional, hanya pada `/admin/anggota`; harus tersedia pada tabel bidang |

Perilaku:

- `/admin/anggota` menampilkan nama, bidang, nomor telepon, dan status seluruh anggota; dapat difilter per bidang.
- `/anggota-bidang-saya` menampilkan nama, jabatan bidang, dan nomor telepon. **Bidang diambil dari akun yang login, bukan dari parameter**; permintaan dengan ID bidang lain menghasilkan respons 403.
- Bidang tanpa anggota menampilkan pesan "Tidak ada anggota yang sesuai" tanpa error.
- Respons tidak memuat kata sandi, hash, atau token.

### 5.7 Tetapkan Ketua dan Wakil Ketua (B-017, B-019)

**Endpoint:** `PUT /admin/bidang/{id}/ketua` dan `PUT /admin/bidang/{id}/wakil-ketua`

| Parameter    | Tipe    | Ketentuan                                           |
| ------------ | ------- | --------------------------------------------------- |
| `id_anggota` | Integer | Wajib diisi, harus anggota aktif pada bidang `{id}` |

Perilaku:

- Satu bidang hanya memiliki satu ketua aktif dan satu wakil ketua aktif `[ASUMSI]`; yang baru menggantikan yang lama.
- Anggota dari bidang lain atau akun nonaktif ditolak dengan pesan umum.
- Wakil ketua tidak boleh orang yang sama dengan ketua.
- Penetapan dicatat di log (bidang dan anggota).

### 5.8 Catat Surat Masuk (B-035)

**Endpoint:** `POST /surat/masuk`

| Parameter          | Tipe   | Ketentuan                                        |
| ------------------ | ------ | ------------------------------------------------ |
| `nomor_surat`      | String | Wajib diisi, unik pada jenis masuk               |
| `tanggal_surat`    | Date   | Wajib diisi                                      |
| `pengirim`         | String | Wajib diisi                                      |
| `perihal`          | String | Wajib diisi                                      |
| `tanggal_diterima` | Date   | Wajib diisi, tidak boleh sebelum `tanggal_surat` |

Kolom wajib adalah `[ASUMSI]`. Pesan validasi: Nomor surat sudah tercatat. / Tanggal diterima tidak boleh sebelum tanggal surat. / (pesan wajib diisi per kolom). Pencatatan dicatat di log (waktu, pencatat, nomor surat).

### 5.9 Catat Surat Keluar (B-036)

**Endpoint:** `POST /surat/keluar`

| Parameter       | Tipe   | Ketentuan                           |
| --------------- | ------ | ----------------------------------- |
| `nomor_surat`   | String | Wajib diisi, unik pada jenis keluar |
| `tanggal_surat` | Date   | Wajib diisi                         |
| `tujuan`        | String | Wajib diisi                         |
| `perihal`       | String | Wajib diisi                         |

Kolom wajib adalah `[ASUMSI]`. Pesan validasi sama dengan surat masuk.

### 5.10 Ubah Data Surat (B-038)

**Endpoint:** `PUT /surat/{id}`

Parameter sama dengan jenis suratnya (surat masuk atau surat keluar).

Perilaku:

- Jenis surat tidak dapat diubah setelah tersimpan `[ASUMSI]`.
- Nomor surat tetap unik per jenis; nomor yang dipakai surat lain ditolak dan data tidak berubah.
- Surat tidak ditemukan menghasilkan respons 404. Perubahan dicatat di log.

### 5.11 Daftar dan Cari Surat (B-039)

**Endpoint:** `GET /surat`

| Parameter | Tipe   | Ketentuan                                                            |
| --------- | ------ | -------------------------------------------------------------------- |
| `q`       | String | Opsional, kata kunci perihal, tidak membedakan huruf besar dan kecil |
| `dari`    | Date   | Opsional, awal rentang tanggal surat (termasuk batas)                |
| `sampai`  | Date   | Opsional, akhir rentang tanggal surat (termasuk batas)               |

Perilaku:

- Pencarian dan rentang tanggal dapat dipadukan.
- Karakter khusus seperti tanda kutip, `%`, dan `_` diperlakukan sebagai teks biasa.
- `dari` lebih besar dari `sampai` ditolak dengan pesan jelas.
- Tanpa hasil menampilkan "Tidak ada surat yang sesuai".

### 5.12 Tambah Program Kerja (B-041)

**Endpoint:** `POST /program-kerja`

| Parameter         | Tipe   | Ketentuan                                        |
| ----------------- | ------ | ------------------------------------------------ |
| `nama_program`    | String | Wajib diisi                                      |
| `deskripsi`       | String | Wajib diisi                                      |
| `tanggal_mulai`   | Date   | Wajib diisi                                      |
| `tanggal_selesai` | Date   | Wajib diisi, tidak boleh sebelum `tanggal_mulai` |

Kolom wajib adalah `[ASUMSI]`.

Perilaku:

- **Bidang program diambil dari akun yang login di server.** Bila klien mengirim `id_bidang`, nilainya diabaikan.
- Program yang valid langsung tampil pada daftar program bidangnya.
- Penambahan dicatat di log (waktu, pembuat, nama program).

### 5.13 Ubah Program Kerja (B-046)

**Endpoint:** `PUT /program-kerja/{id}`

Parameter sama dengan 5.12. Program milik bidang lain menghasilkan respons 403 dan data tidak berubah. Program tidak ditemukan menghasilkan respons 404. Perubahan dicatat di log.

### 5.14 Daftar Program Kerja (B-042, B-044, B-045)

**Endpoint:** `GET /program-kerja/bidang-saya`, `GET /program-kerja/bidang-lain`, `GET /admin/program-kerja`

| Parameter | Tipe    | Ketentuan                                   |
| --------- | ------- | ------------------------------------------- |
| `bidang`  | Integer | Opsional, hanya pada `/admin/program-kerja` |

Perilaku:

- `bidang-saya`: bidang dari akun yang login; ID bidang lain ditolak (403).
- `bidang-lain`: hanya baca, tanpa aksi ubah atau hapus; permintaan ubah ke program bidang lain ditolak di server.
- `admin/program-kerja`: seluruh bidang dengan filter bidang; peran selain Admin dan Penasihat ditolak (403).
- Bidang tanpa program menampilkan "Belum ada program kerja" tanpa error.

### 5.15 Tambah Kegiatan (B-048)

**Endpoint:** `POST /program-kerja/{id}/kegiatan`

| Parameter       | Tipe   | Ketentuan                                                  |
| --------------- | ------ | ---------------------------------------------------------- |
| `nama_kegiatan` | String | Wajib diisi                                                |
| `tanggal`       | Date   | Wajib diisi, harus berada dalam periode program `[ASUMSI]` |
| `deskripsi`     | String | Wajib diisi                                                |

Perilaku:

- Hanya Pimpinan Bidang pemilik program; program bidang lain menghasilkan respons 403.
- Kegiatan tersimpan dengan relasi ke program yang benar dan tidak muncul pada program lain.
- Penambahan dicatat di log tanpa data sensitif.

## 6. Perilaku Respons dan Pesan Error Umum

Format pesan error umum Sprint 2 mengikuti Sprint 1 (tanpa JSON).

| Kondisi                                                                      | Perilaku                                                                                         |
| ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| Berhasil menampilkan halaman                                                 | Mengembalikan halaman Blade                                                                      |
| Berhasil menyimpan atau memperbarui                                          | Redirect ke halaman tujuan dengan pesan flash sukses                                             |
| Validasi gagal                                                               | Kembali ke form dengan pesan validasi per kolom dan input lama (`withInput`), data tidak berubah |
| Aturan bisnis dilanggar (mis. pindah ke bidang yang sama, nomor surat ganda) | Kembali ke form dengan pesan jelas                                                               |
| Tidak terautentikasi                                                         | Redirect ke halaman login oleh middleware autentikasi                                            |
| Tidak memiliki hak akses (peran, bidang, atau pemilik salah)                 | Respons 403                                                                                      |
| Data tidak ditemukan                                                         | Respons 404                                                                                      |
| Kesalahan server                                                             | Respons 500, halaman error umum                                                                  |

Aturan pesan error:

1. Pengguna hanya melihat pesan umum berbahasa Indonesia.
2. **Dilarang** menampilkan stack trace, query SQL, nama tabel atau kolom internal, path berkas, kata sandi, atau token.
3. Detail teknis hanya masuk ke log (`storage/logs`).
4. Halaman error 403, 404, dan 500 memakai tampilan umum yang sama (bukan halaman debug).
5. Pada lingkungan demo dan produksi `APP_DEBUG=false`.

Contoh pesan standar: "Anda tidak memiliki akses." / "Data tidak ditemukan." / "Terjadi kesalahan. Silakan coba lagi." / "Nomor surat sudah tercatat."

## 7. Catatan

### 7.1 Catatan dari Sprint Review Sprint 1 (Bu Sri)

- Peran **Penasihat** yang lama akan diganti menjadi **Penanggung Jawab**.
- Peran **Penasihat** baru ditambahkan, tidak terikat bidang, kemungkinan hanya dapat melihat (menunggu konfirmasi).
- Nama peran pada middleware (`role:...`) dan kolom akses dokumen ini bisa berubah mengikuti keputusan tersebut. Simpan daftar peran per fitur di satu tempat konfigurasi agar mudah diubah.
- Status akun aktif/nonaktif dan aturan hapus akun masih menunggu konfirmasi klien (berkaitan dengan B-012 dan B-013).

### 7.2 Catatan implementasi

- Nama peran pada seeder Sprint 1 adalah `Penasihat` (dengan "i"); lembar Backlog menulis "Penasehat". Samakan penulisan sebelum dipakai di middleware.
- Akses persuratan berbasis **bidang** (Sekretariat), sedangkan `role:` Sprint 1 berbasis peran. Perlu middleware atau policy tambahan. Sesuai Sprint Review, Admin otomatis berbidang Sekretariat.

## 8. Pengujian

Setiap endpoint diuji dengan unit test (skenario berhasil, validasi gagal, akses ditolak) dan uji manual. Pengujian dijalankan dengan:

```bash
php artisan test
```

## 9. Riwayat Perubahan

| Tanggal     | Perubahan                         | Oleh  |
| ----------- | --------------------------------- | ----- |
| 10 Nov 2026 | Draf awal kontrak Sprint 2 (T-21) | Manda |

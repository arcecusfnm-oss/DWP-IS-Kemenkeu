# Desain Skema Database dan Kontrak API Sprint 3

**Task:** B-018 T-001  
**Owner:** Fahira Putri Ananta  
**Estimasi:** 1.25 jam  
**Status:** Draft desain berdasarkan Backlog dan Acceptance Criteria Sprint 3  
**Tanggal:** 23 November 2026

## 1. Ruang lingkup

Draft kontrak endpoint dan desain skema tambahan untuk riwayat perpindahan (B-029), berkas surat (B-037), SK (B-128), persetujuan/revisi program kerja (B-051, B-123, B-124), dan templat laporan kegiatan (B-052). Ini desain lintas PBI, belum implementasi migration, model, controller, route, UI, atau database.

## 2. Tabel existing yang dipakai

| Tabel | Pemakaian |
|---|---|
| `anggota`, `bidang`, `pengguna` | Mutasi, relasi bidang, aktor |
| `surat_masuk`, `surat_keluar` | Metadata berkas surat |
| `surat_keputusan` | Catatan SK (migration sudah ada; perlu koordinasi karena backlog T-073 menyebut migration SK) |
| `program_kerja` | Status, tanggal persetujuan, penyetuju, catatan revisi |
| `kegiatan` | Relasi laporan kegiatan (task laporan B-053) |

## 3. Usulan skema

### 3.1 Riwayat perpindahan anggota (B-029) — tabel baru `riwayat_perpindahan_anggota`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id_riwayat_perpindahan` | BIGINT PK | Auto increment |
| `id_anggota` | FK anggota | Wajib |
| `id_bidang_lama` | FK bidang | Bidang asal |
| `nama_bidang_lama` | VARCHAR | Snapshot nama bidang agar historis tetap terbaca |
| `id_bidang_baru` | FK bidang | Bidang tujuan |
| `nama_bidang_baru` | VARCHAR | Snapshot nama bidang |
| `tanggal_pindah` | DATE | Tanggal mutasi |
| `dipindahkan_oleh` | FK pengguna | Pelaku mutasi |
| `created_at` | TIMESTAMP | Waktu pencatatan |

AC: setiap pindah menambah histori; histori read-only tanpa update/delete; mutasi dan insert histori atomik; empty state “Belum ada riwayat perpindahan”; Admin-only; terbaru dahulu; nama anggota/bidang historis tetap terbaca saat nama bidang berubah.

### 3.2 Berkas surat (B-037) — tambah kolom pada `surat_masuk` dan `surat_keluar`

Tidak ada tabel berkas generik. Usulan kolom pada kedua tabel:

| Kolom | Tipe | Catatan |
|---|---|---|
| `berkas_nama_asli` | VARCHAR(255) nullable | Nama file asli |
| `berkas_path` | VARCHAR(500) nullable | Path penyimpanan privat dengan nama acak |
| `berkas_ukuran` | BIGINT nullable | Bytes |
| `berkas_mime` | VARCHAR(100) nullable | MIME terverifikasi |
| `berkas_diunggah_oleh` | FK pengguna nullable | Pengunggah |
| `berkas_diunggah_pada` | TIMESTAMP nullable | Waktu upload |

AC: satu PDF per catatan surat; upload replacement mengganti berkas lama; non-PDF, PDF palsu dari ekstensi, dan file melebihi batas ditolak; simpan non-public dengan nama acak; buka hanya lewat endpoint terotorisasi Admin; akses path langsung ditolak; nama asli di-escape; log upload/open (waktu, pelaku, ID surat), tidak log isi file; gagal upload tidak meninggalkan metadata atau file yatim. Batas ukuran perlu ditetapkan/dirujuk ke konfigurasi.

### 3.3 SK (B-128) — reuse `surat_keputusan`

Migration existing sudah memiliki:
- `id_surat_keputusan` (PK)
- `nomor_sk` (unik)
- `tanggal`
- `perihal`
- `status_penetapan` enum `sudah`, `belum`, `batal` (default `belum`)
- `dibuat_oleh` (FK pengguna), timestamps, index tanggal

AC: empat field form wajib; pilihan penetapan tetap; nomor unik; tanggal valid; daftar menampilkan data dan search/filter nomor/perihal; empty search “Data tidak ditemukan”; Admin-only sementara (menunggu konfirmasi); escape output dan log; CSRF. Backlog T-073 meminta migration SK, sehingga perlu diselaraskan dengan migration existing sebelum implementasi agar tidak membuat tabel duplikat.

### 3.4 Persetujuan program kerja (B-051, B-123, B-124) — tambah kolom pada `program_kerja`

| Kolom | Tipe | Catatan |
|---|---|---|
| `status` | Existing string | Nilai workflow `Diajukan`, `Disetujui`, `Perlu Revisi` |
| `tanggal_persetujuan` | DATETIME nullable | Waktu persetujuan |
| `disetujui_oleh` | FK pengguna nullable | Admin penyetuju |
| `catatan_revisi` | TEXT nullable | Catatan pengembalian |

AC: Admin melihat antrean Diajukan semua bidang, dapat filter bidang (nama bidang, program, tanggal pengajuan); setujui hanya dari Diajukan, status Disetujui dan tanggal/nama penyetuju tercatat; program selain Diajukan dan ID tidak ada ditolak; hanya Admin (sementara menunggu konfirmasi), log; Revisi hanya pada Diajukan, catatan wajib 1–1000 karakter, status Perlu Revisi; pemilik bidang melihat catatan; program dapat diedit dan diajukan ulang via B-046; catatan revisi hanya untuk pemilik/Admin; setelah re-submit atau approve catatan lama tidak lagi aktif. Riwayat keputusan immutable tidak disebut di task ini—jangan tambah tabel tanpa keputusan.

### 3.5 Templat laporan kegiatan (B-052) — tabel baru `template_laporan`

Templat satu dan global untuk seluruh bidang (bukan per-bidang). Usulan tabel singleton:

| Kolom | Tipe | Catatan |
|---|---|---|
| `id_template_laporan` | BIGINT PK | ID |
| `label_judul_kegiatan` | VARCHAR(255) | Kolom wajib |
| `label_tanggal_kegiatan` | VARCHAR(255) | Kolom wajib |
| `label_penjelasan_singkat` | VARCHAR(255) | Label kolom |
| `label_dokumentasi` | VARCHAR(255) | Label/slot dokumentasi |
| `diubah_oleh` | FK pengguna | Admin pengubah |
| `created_at`, `updated_at` | TIMESTAMP | Laravel |

AC: template punya Judul Kegiatan, Tanggal Kegiatan, Penjelasan Singkat, Dokumentasi; satu template sama di semua bidang; Judul dan Tanggal wajib dan tidak boleh dihapus/dikosongkan; Admin-only untuk pengelolaan, perubahan tercatat log, CSRF; perubahan tidak mengubah laporan lama. Struktur ini masih usulan—perlu review apakah label saja atau konfigurasi tambahan disimpan.

## 4. Relasi

- Riwayat: anggota, bidang asal, bidang tujuan, dan pengguna pelaku.
- Metadata berkas pada surat masuk/keluar: pengunggah FK pengguna.
- SK: pengguna pencatat.
- Program kerja: pengguna penyetuju.
- Template: pengguna pengubah.
- Laporan kegiatan (B-053) adalah tabel terpisah dalam task lain; laporan perlu menyimpan snapshot isian supaya perubahan template tidak mengubah laporan yang telah tersimpan.

## 5. Draft kontrak endpoint web Laravel

Endpoint usulan, belum dibuat. Ikuti session/auth, middleware role, CSRF, validasi, dan pola response/redirect web project.

### Riwayat
| Method | Endpoint | Fungsi | Akses |
|---|---|---|---|
| GET | `/admin/anggota/{id}/riwayat-perpindahan` | List terbaru; empty state bila belum ada | Admin |
| PUT | `/admin/anggota/{id}/pindah-bidang` | Route existing, diperluas insert histori dalam transaction | Admin |

Tidak ada endpoint create histori terpisah, update histori, atau delete histori.

### Berkas surat
| Method | Endpoint usulan | Fungsi | Akses |
|---|---|---|---|
| POST | `/surat/{jenis}/{id}/berkas` | Upload/ganti satu PDF (`jenis`: `masuk` atau `keluar`) | Admin |
| GET | `/surat/{jenis}/{id}/berkas` | Buka file lewat sistem dengan authorization | Admin |

### SK
| Method | Endpoint usulan | Fungsi | Akses |
|---|---|---|---|
| GET | `/admin/surat-keputusan` | List dan cari/filter nomor/perihal | Admin* |
| POST | `/admin/surat-keputusan` | Catat SK | Admin* |

*Role Admin masih asumsi AC, menunggu konfirmasi.

### Persetujuan program kerja
| Method | Endpoint usulan | Fungsi | Akses |
|---|---|---|---|
| GET | `/admin/program-kerja/persetujuan` | Antrean Diajukan; filter bidang | Admin* |
| POST | `/admin/program-kerja/{id}/setujui` | Setujui, rekam tanggal/penyetuju | Admin* |
| POST | `/admin/program-kerja/{id}/revisi` | Kembalikan dengan catatan wajib | Admin* |
| GET | `/program-kerja/bidang-saya` | Status program dan catatan revisi milik bidang | Pimpinan/Anggota Bidang |
*Role Admin sementara, menunggu konfirmasi.

### Templat laporan
| Method | Endpoint usulan | Fungsi | Akses |
|---|---|---|---|
| GET | `/admin/template-laporan` | Lihat konfigurasi global | Admin |
| PUT | `/admin/template-laporan` | Ubah label template global | Admin |
| GET | `/template-laporan` | Lihat/gunakan template global | Pengguna sesuai hak akses laporan |

Tidak ada template per bidang. Endpoint download/upload template file tidak disyaratkan oleh B-052.

## 6. Payload usulan

### Mutasi anggota
```json
{"id_bidang_tujuan": 3}
```
Tanggal dan pelaku diambil server.

### Upload/ganti berkas surat
Multipart form dengan field `file` (PDF). Jenis dan ID surat dari URL; metadata dicatat server.

### SK
```json
{"nomor_sk":"SK-001/2026","tanggal":"2026-11-23","perihal":"Penetapan Pengurus","status_penetapan":"sudah"}
```

### Revisi program
```json
{"catatan_revisi":"Mohon sesuaikan anggaran dan jadwal."}
```

### Persetujuan
Tidak memerlukan payload keputusan; waktu dan penyetuju ditentukan server.

### Template
```json
{"label_judul_kegiatan":"Judul Kegiatan","label_tanggal_kegiatan":"Tanggal Kegiatan","label_penjelasan_singkat":"Penjelasan Singkat","label_dokumentasi":"Dokumentasi"}
```

## 7. Keamanan dan aturan umum

- Server-side auth/authorization; role non-Admin tidak boleh mengakses tindakan Admin.
- CSRF untuk perubahan data.
- Validasi upload PDF berdasarkan tipe dan isi, ukuran; storage privat dengan nama acak.
- Output escape, ORM/query parameter, log peristiwa relevan tanpa data sensitif.
- Mutasi anggota dan histori satu transaction.
- Persetujuan/revisi hanya untuk status Diajukan; catatan revisi 1–1000 karakter.
- Histori perpindahan tidak dapat diedit/dihapus.

## 8. Hal untuk review lintas PBI

- Role Admin untuk SK dan approval masih asumsi AC; konfirmasi.
- Batas ukuran PDF dan pesan validasi.
- Kebijakan file lama saat replacement (hapus atau arsip) serta cleanup jika gagal upload.
- FK delete/retention policy untuk histori perpindahan.
- Bentuk konfigurasi template (label saja atau metadata/urutan lain).
- Approval perlu histori keputusan immutable atau cukup kolom pada `program_kerja`.
- Migration SK existing versus T-073 agar tidak duplicate table.
- Keselarasan nilai status existing `program_kerja` dengan workflow Sprint 3.

## 9. Status

Draft T-001 untuk review lintas PBI berdasarkan Backlog, Acceptance Criteria, dan Lembar Tugas Sprint 3. Belum ada migration atau perubahan database.

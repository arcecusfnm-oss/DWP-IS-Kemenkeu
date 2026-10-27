Rancangan Panduan Kontribusi Proyek

1. Tujuan
Dokumen ini mengatur penggunaan Git dan GitHub selama pengembangan sistem agar perubahan kode dapat dikelola dan ditinjau oleh tim.


### 2. Aturan Branch
- **`main`**: Branch utama yang berisi perubahan yang sudah ditinjau dan disetujui.
- **`feature/nama-fitur`**: Branch untuk mengembangkan fitur baru.
- **`fix/nama-perbaikan`**: Branch untuk memperbaiki *bug*.
- Setiap anggota mengerjakan tugas pada branch masing-masing, bukan langsung pada `main`.

Contoh nama branch:
- `feature/login`
- `feature/user-management`
- `fix/login-validation`


### 3. Aturan Pull Request
1. Buat branch dari `main` terbaru.
2. Kerjakan perubahan pada branch tersebut.
3. Commit perubahan dengan pesan yang menjelaskan pekerjaan.
4. Push branch ke GitHub.
5. Buat Pull Request menuju `main`.
6. Jelaskan tujuan perubahan dan cantumkan kartu Trello terkait.
7. Minta review anggota tim yang ditunjuk.
8. Gabungkan perubahan setelah disetujui dan konflik diselesaikan.


### 4. Aturan Commit
Gunakan pesan commit yang singkat dan menjelaskan perubahan.

Contoh pesan commit:
- `docs: add project architecture`
- `docs: add contribution guidelines`
- `feat: implement login`
- `fix: handle login validation`


### 5. Ketentuan Umum
- Jangan memasukkan password, token, API key, atau rahasia lain ke repository.
- Jangan mengunggah file `.env`.
- Perubahan yang belum ditinjau tidak boleh langsung digabungkan ke `main`.
- Jika terjadi konflik, selesaikan dan periksa kembali perubahan sebelum melakukan *merge*.

*Catatan: Aturan ini merupakan usulan awal dan perlu disepakati oleh tim.*

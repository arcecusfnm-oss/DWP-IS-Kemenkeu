Rancangan Arsitektur Website ARCECUS

1. Tujuan
Dokumen ini berisi rancangan awal arsitektur website ARCECUS. Website direncanakan menggunakan Laravel sebagai framework backend dan MySQL sebagai database.
Rancangan ini masih bersifat sementara dan dapat disesuaikan berdasarkan hasil diskusi tim serta konfirmasi Product Owner (PO).


2. Teknologi yang Digunakan

| Komponen | Teknologi | Fungsi |
| :--- | :--- | :--- |
| Backend | Laravel | Mengelola logika aplikasi dan permintaan pengguna |
| Database | MySQL | Menyimpan data sistem |
| Frontend | HTML, CSS, JavaScript | Membuat tampilan dan interaksi website |
| UI Framework | Bootstrap | Membantu membuat tampilan website yang responsif |
| Lingkungan Pengembangan | Herd atau Laragon | Menjalankan aplikasi di komputer pengembang |

Catatan: Tim perlu mengonfirmasi apakah akan menggunakan Herd atau Laragon sebagai lingkungan pengembangan utama.


### 3. Gambaran Arsitektur Sistem
Pengguna mengakses website melalui browser. Permintaan diproses oleh Laravel. Jika membutuhkan data, Laravel berkomunikasi dengan database MySQL. Hasil pemrosesan kemudian dikirim kembali kepada pengguna melalui browser.

- Alur utama: Pengguna → Browser → Laravel → MySQL
- Alur respons: MySQL → Laravel → Browser → Pengguna


### 4. Komponen Sistem

#### 4.1 Frontend
Frontend menampilkan halaman website, formulir login, pesan validasi, dan informasi yang dapat diakses pengguna. Teknologi yang direncanakan adalah HTML, CSS, JavaScript, dan Bootstrap.

#### 4.2 Backend
Laravel menangani permintaan pengguna, validasi input, autentikasi, pemeriksaan status akun, pengelolaan sesi, dan komunikasi dengan database sesuai kebutuhan sistem.

#### 4.3 Database
- MySQL digunakan untuk menyimpan data yang dibutuhkan sistem, termasuk data akun sesuai kebutuhan yang telah disepakati.
- Password harus disimpan dalam bentuk hash, bukan teks biasa.

#### 4.4 Autentikasi dan Sesi
- Proses login perlu memeriksa kredensial pengguna dan memastikan akun memenuhi ketentuan akses. Setelah login berhasil, sistem mengelola sesi pengguna.
- Pengaturan cookie, pembatasan percobaan login, dan pencatatan aktivitas perlu memperhatikan persyaratan keamanan sistem.


### 5. Hal yang Perlu Dikonfirmasi
- Apakah tim menggunakan Herd atau Laragon?
- Apakah autentikasi menggunakan sesi Laravel atau mekanisme lain?
- Apa saja peran pengguna dan hak aksesnya?
- Apakah seluruh fitur pengelolaan akun termasuk dalam cakupan B-001?
- Apakah ada standar teknis tambahan dari PO?


### 6. Status Dokumen
Draft awal. Rancangan akan ditinjau oleh tim dan disesuaikan setelah keputusan teknis yang belum pasti dikonfirmasi.


### 7. Rancangan Endpoint Autentikasi
Rancangan endpoint berikut dibatasi pada kebutuhan login berdasarkan user story B-001. Endpoint merupakan rancangan awal dan perlu disesuaikan dengan implementasi Laravel serta keputusan tim.

| Method | Endpoint | Fungsi | Hak Akses |
| :--- | :--- | :--- | :--- |
| GET | /login | Menampilkan halaman login | Pengguna yang belum login |
| POST | /login | Memvalidasi nama pengguna dan kata sandi, memeriksa status akun, serta membuat sesi jika login berhasil | Pengguna yang belum login |
| POST | /logout | Mengakhiri sesi pengguna yang sedang login | Pengguna yang sudah login |


#### 7.1 Ketentuan Endpoint Login
- Nama pengguna dan kata sandi harus diisi.
- Kredensial yang salah ditolak dengan pesan `Nama pengguna atau kata sandi salah`.
- Akun nonaktif ditolak dengan pesan `Akun tidak aktif`.
- Login berhasil mengarahkan pengguna ke halaman dasbor.
- Sistem membatasi percobaan login setelah lima kegagalan berturut-turut selama lima menit.
- Sistem tidak menampilkan kata sandi atau token pada pesan kesalahan maupun log.


#### 7.2 Catatan Implementasi
- Endpoint di atas merupakan rancangan untuk aplikasi web Laravel. Jika tim menggunakan autentikasi berbasis sesi bawaan Laravel, halaman login dapat menggunakan route web dan session Laravel tanpa harus membuat REST API terpisah.
- Penggunaan endpoint logout dan metode HTTP-nya perlu mengikuti mekanisme autentikasi serta perlindungan CSRF yang diterapkan pada aplikasi.
- Daftar endpoint ini belum menjadi implementasi final dan perlu ditinjau oleh tim.


### 8. Rancangan Request, Response, HTTP Status, dan Error
Rancangan ini menggunakan pendekatan website Laravel dengan form login dan session. Contoh berikut merupakan rancangan perilaku aplikasi, bukan implementasi yang sudah berjalan.

#### 8.1 Request Login
Pengguna mengirimkan nama pengguna dan kata sandi melalui form login.
- **Method:** `POST`
- **Endpoint:** `/login`
- **Content-Type:** `application/x-www-form-urlencoded`

Contoh data yang dikirim:
`username=fahira&password=contoh_password`

*Catatan: Data di atas hanya contoh. Kata sandi asli tidak boleh dicatat ke log.*

#### 8.2 Response Login Berhasil
Jika nama pengguna dan kata sandi benar serta akun aktif, sistem membuat sesi pengguna dan mengarahkan pengguna ke halaman dasbor.
- **HTTP Status:** `302 Found` (redirect)
- **Tujuan redirect:** `/dashboard`

#### 8.3 Response Login Gagal
Jika nama pengguna tidak terdaftar atau kata sandi salah, sistem menolak login dan menampilkan pesan yang sama.
- **HTTP Status:** Umumnya `302 Found` untuk form login berbasis session Laravel.
- **Pesan:** `Nama pengguna atau kata sandi salah`

Jika akun ditemukan tetapi berstatus nonaktif, sistem menolak login dengan pesan: `Akun tidak aktif`

Untuk input yang kosong atau tidak valid, Laravel dapat mengembalikan pengguna ke halaman login beserta pesan validasi.

#### 8.4 HTTP Status yang Digunakan

| HTTP Status | Kegunaan |
| :--- | :--- |
| **200 OK** | Halaman berhasil ditampilkan |
| **302 Found** | Mengarahkan pengguna ke halaman lain setelah proses form |
| **419 Page Expired** | Sesi atau token CSRF tidak valid atau kedaluwarsa |
| **422 Unprocessable Content** | Umumnya digunakan untuk kesalahan validasi pada request API |
| **429 Too Many Requests** | Permintaan ditolak karena batas percobaan login terlampaui |
| **500 Internal Server Error** | Terjadi kesalahan internal server |

*Status yang benar-benar dikembalikan perlu mengikuti implementasi dan konfigurasi Laravel.*

#### 8.5 Format Error Umum
Untuk website berbasis form dan session, kesalahan ditampilkan melalui pesan pada halaman login.

| Kondisi | Pesan yang Ditampilkan |
| :--- | :--- |
| Nama pengguna tidak terdaftar atau kata sandi salah | Nama pengguna atau kata sandi salah |
| Akun nonaktif | Akun tidak aktif |
| Nama pengguna atau kata sandi kosong | Nama pengguna dan kata sandi wajib diisi |
| Percobaan login melewati batas | Pesan umum bahwa percobaan login terlalu banyak |
| Kesalahan internal server | Pesan umum bahwa sistem mengalami gangguan |

*Pesan kesalahan tidak boleh menampilkan kata sandi, token, query database, atau detail teknis internal.*

#### 8.6 Catatan
- Rancangan ini menggunakan pola respons website berbasis form dan session, sehingga tidak semua respons berupa JSON. Jika tim memutuskan menggunakan REST API yang mengembalikan JSON, format request, response, dan status HTTP perlu disesuaikan.
- Pesan dan perilaku yang berkaitan dengan keamanan harus mengikuti acceptance criteria B-001.


### 9. Kebutuhan Keamanan Login
Kebutuhan keamanan berikut mengacu pada delapan *acceptance criteria* user story B-001.

#### 9.1 Validasi Kredensial dan Status Akun
- Login hanya berhasil apabila nama pengguna dan kata sandi benar serta akun berstatus aktif.
- Apabila nama pengguna tidak terdaftar atau kata sandi salah, sistem menampilkan pesan yang sama: `Nama pengguna atau kata sandi salah`.
- Apabila akun berstatus nonaktif, sistem menampilkan pesan `Akun tidak aktif`.
- Input kosong dan karakter khusus harus ditangani dengan aman tanpa menyebabkan error server.

#### 9.2 Penyimpanan Kata Sandi
- Kata sandi disimpan dalam bentuk *hash*, bukan teks asli.
- Proses verifikasi kata sandi menggunakan mekanisme verifikasi *hash* yang sesuai dengan Laravel.
- Kata sandi tidak boleh ditulis ke *log*.

#### 9.3 Keamanan Sesi
- Cookie sesi harus memiliki atribut `Secure` dan `HttpOnly`.
- Sesi berakhir otomatis setelah 30 menit tanpa aktivitas.
- Sesi hanya dibuat setelah autentikasi berhasil.
- Logout harus mengakhiri sesi pengguna.

#### 9.4 Pembatasan Percobaan Login
- Setelah lima kali gagal login berturut-turut, percobaan berikutnya ditolak selama lima menit.
- Penolakan tersebut menampilkan pesan umum.
- Pembatasan harus diterapkan di sisi server, bukan hanya melalui tampilan *frontend*.

#### 9.5 Keamanan Pemrosesan Input
- Query database menggunakan ORM Laravel atau *prepared statement*.
- Input pengguna harus divalidasi sebelum diproses.
- Kesalahan tidak boleh menampilkan query database, detail internal server, atau informasi sensitif.

#### 9.6 Pencatatan Aktivitas Login
- Login berhasil dan gagal harus dicatat ke *log*.
- Log mencakup waktu kejadian dan nama pengguna sesuai *acceptance criteria*.
- Log tidak boleh memuat kata sandi atau token.
- Akses ke log perlu dibatasi kepada pihak yang berwenang.

#### 9.7 Ringkasan Pemetaan Acceptance Criteria

| AC B-001 | Kebutuhan Keamanan |
| :--- | :--- |
| **AC 1** | Login berhasil untuk kredensial benar dan akun aktif; diarahkan ke dasbor |
| **AC 2** | Kredensial salah menghasilkan pesan umum yang sama |
| **AC 3** | Akun nonaktif ditolak dengan pesan `Akun tidak aktif` |
| **AC 4** | Kata sandi disimpan sebagai *hash* |
| **AC 5** | Cookie `Secure` dan `HttpOnly`; sesi berakhir setelah 30 menit tidak aktif |
| **AC 6** | Lima kegagalan berturut-turut menyebabkan penolakan selama lima menit |
| **AC 7** | Input ditangani aman, menggunakan ORM atau *prepared statement*, dan tidak menimbulkan error server |
| **AC 8** | Login berhasil dan gagal dicatat tanpa kata sandi atau token |

*Catatan: Bagian ini mendokumentasikan persyaratan. Pemenuhan keamanan baru dapat dinyatakan setelah implementasi dan pengujian dilakukan.*

Rancangan Arsitektur Website ARCECUS

1. Tujuan
Dokumen ini berisi rancangan awal arsitektur website ARCECUS. Website direncanakan menggunakan Laravel sebagai framework backend dan MySQL sebagai database.
Rancangan ini masih bersifat sementara dan dapat disesuaikan berdasarkan hasil diskusi tim serta konfirmasi Product Owner (PO).


2. Teknologi yang Digunakan
| Komponen                | Teknologi             | Fungsi |
|-------------------------|-----------------------|---------------------------------------------------|
| Backend                 | Laravel               | Mengelola logika aplikasi dan permintaan pengguna |
| Database                | MySQL                 | Menyimpan data sistem                             |
| Frontend                | HTML, CSS, JavaScript | Membuat tampilan dan interaksi website            |
| UI Framework            | Bootstrap             | Membantu membuat tampilan website yang responsif  |
| Lingkungan Pengembangan | Herd atau Laragon     | Menjalankan aplikasi di komputer pengembang       |
Catatan: Tim perlu mengonfirmasi apakah akan menggunakan Herd atau Laragon sebagai lingkungan pengembangan utama.


3. Gambaran Arsitektur Sistem
Pengguna mengakses website melalui browser. Permintaan diproses oleh Laravel. Jika membutuhkan data, Laravel berkomunikasi dengan database MySQL. Hasil pemrosesan kemudian dikirim kembali kepada pengguna melalui browser.
	- Alur utama:
		Pengguna → Browser → Laravel → MySQL
	- Alur respons:
		MySQL → Laravel → Browser → Pengguna


4. Komponen Sistem
	- 4.1 Frontend
		Frontend menampilkan halaman website, formulir login, pesan validasi, dan informasi yang dapat diakses pengguna. Teknologi yang direncanakan adalah HTML, CSS, JavaScript, dan Bootstrap.
	- 4.2 Backend
		Laravel menangani permintaan pengguna, validasi input, autentikasi, pemeriksaan status akun, pengelolaan sesi, dan komunikasi dengan database sesuai kebutuhan sistem.
	- 4.3 Database
		- MySQL digunakan untuk menyimpan data yang dibutuhkan sistem, termasuk data akun sesuai kebutuhan yang telah disepakati.
		- Password harus disimpan dalam bentuk hash, bukan teks biasa.
	- 4.4 Autentikasi dan Sesi
		- Proses login perlu memeriksa kredensial pengguna dan memastikan akun memenuhi ketentuan akses. Setelah login berhasil, sistem mengelola sesi pengguna.
		- Pengaturan cookie, pembatasan percobaan login, dan pencatatan aktivitas perlu memperhatikan persyaratan keamanan sistem.


5. Hal yang Perlu Dikonfirmasi
	- Apakah tim menggunakan Herd atau Laragon?
	- Apakah autentikasi menggunakan sesi Laravel atau mekanisme lain?
	- Apa saja peran pengguna dan hak aksesnya?
	- Apakah seluruh fitur pengelolaan akun termasuk dalam cakupan B-001?
	- Apakah ada standar teknis tambahan dari PO?


6. Status Dokumen
Draft awal. Rancangan akan ditinjau oleh tim dan disesuaikan setelah keputusan teknis yang belum pasti dikonfirmasi.


7. Rancangan Endpoint Autentikasi
Rancangan endpoint berikut dibatasi pada kebutuhan login berdasarkan user story B-001. Endpoint merupakan rancangan awal dan perlu disesuaikan dengan implementasi Laravel serta keputusan tim.

| Method | Endpoint | Fungsi                                                                                                  | Hak Akses                 |
|--------|----------|---------------------------------------------------------------------------------------------------------|---------------------------|
| GET    | /login   | Menampilkan halaman login                                                                               | Pengguna yang belum login |
| POST   | /login   | Memvalidasi nama pengguna dan kata sandi, memeriksa status akun, serta membuat sesi jika login berhasil | Pengguna yang belum login |
| POST   | /logout  | Mengakhiri sesi pengguna yang sedang login                                                              | Pengguna yang sudah login |
	- 7.1 Ketentuan Endpoint Login
		- Nama pengguna dan kata sandi harus diisi.
		- Kredensial yang salah ditolak dengan pesan `Nama pengguna atau kata sandi salah`.
		- Akun nonaktif ditolak dengan pesan `Akun tidak aktif`.
		- Login berhasil mengarahkan pengguna ke halaman dasbor.
		- Sistem membatasi percobaan login setelah lima kegagalan berturut-turut selama lima menit.
		- Sistem tidak menampilkan kata sandi atau token pada pesan kesalahan maupun log.
	- 7.2 Catatan Implementasi
		- Endpoint di atas merupakan rancangan untuk aplikasi web Laravel. Jika tim menggunakan autentikasi berbasis sesi bawaan Laravel, halaman login dapat menggunakan route web dan session Laravel tanpa harus membuat REST API terpisah.
		- Penggunaan endpoint logout dan metode HTTP-nya perlu mengikuti mekanisme autentikasi serta perlindungan CSRF yang diterapkan pada aplikasi.
		- Daftar endpoint ini belum menjadi implementasi final dan perlu ditinjau oleh tim.

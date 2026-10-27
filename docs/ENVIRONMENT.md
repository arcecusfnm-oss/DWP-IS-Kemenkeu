Rancangan Panduan Environment Variable

1. Tujuan
Environment variable digunakan untuk mengatur konfigurasi aplikasi tanpa menyimpan informasi sensitif langsung di dalam kode program.


### 2. Teknologi
- **Framework:** Laravel
- **Database:** MySQL
- **File Konfigurasi Lokal:** `.env`
- **Contoh Konfigurasi:** `.env.example`


### 3. Konfigurasi Database
Variabel yang diperlukan untuk koneksi database Laravel:
- `DB_CONNECTION`: Jenis database, yaitu `mysql`
- `DB_HOST`: Alamat server database
- `DB_PORT`: Port database
- `DB_DATABASE`: Nama database proyek
- `DB_USERNAME`: Username database
- `DB_PASSWORD`: Password database

*Nilai aktual disesuaikan dengan konfigurasi lingkungan pengembangan masing-masing anggota tim.*


### 4. Ketentuan Keamanan
1. File `.env` **tidak boleh** diunggah ke repository.
2. Informasi password, token, dan API key tidak boleh dimasukkan ke kode atau commit.
3. File `.env.example` digunakan sebagai contoh nama variabel tanpa memuat password atau data rahasia yang sebenarnya.
4. Setiap anggota membuat konfigurasi `.env` lokal sesuai lingkungan pengembangan masing-masing.
5. Konfigurasi produksi harus menggunakan nilai yang aman dan dikelola sesuai kebijakan tim.


### 5. Status
Struktur proyek Laravel telah disiapkan dan konfigurasi database MySQL telah diuji pada lingkungan pengembangan lokal. Setiap anggota tim tetap perlu menyiapkan file .env sesuai konfigurasi lokal masing-masing. File .env.example menjadi acuan variabel konfigurasi tanpa menyimpan informasi rahasia.

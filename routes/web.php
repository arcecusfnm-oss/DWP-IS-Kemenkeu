<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Admin\BidangController;
use App\Http\Controllers\Admin\AkunController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Penasihat\DashboardController as PenasihatDashboardController;
use App\Support\PeranProgramKerja;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\NoCache;
use App\Http\Controllers\ProgramKerjaController;
use App\Http\Controllers\Admin\AnggotaController;
use App\Http\Controllers\Admin\KetuaBidangController;
use App\Http\Middleware\AksesPersuratan;
use App\Http\Controllers\Admin\PencarianSuratController;
use App\Http\Controllers\AnggotaBidangController;
use App\Http\Controllers\Admin\UbahSuratController;
use App\Http\Controllers\Admin\CatatSuratMasukController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\Admin\WakilKetuaBidangController;

// Halaman utama (publik)
Route::view('/', 'landing')
    ->middleware(NoCache::class)
    ->name('beranda');

// Halaman login
Route::get('/login', [LoginController::class, 'showLogin'])
    ->middleware('guest')
    ->name('login');

// Proses login
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('guest')
    ->name('login.process');

// Logout
Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Dashboard admin
Route::get('/admin/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'role:Admin', NoCache::class])
    ->name('admin.dashboard');

// Kelola Bidang
Route::middleware(['auth', 'role:Admin', NoCache::class])
    ->prefix('admin/bidang')
    ->name('admin.bidang.')
    ->group(function () {
        Route::get('/', [BidangController::class, 'index'])->name('index');
        Route::get('/tambah', [BidangController::class, 'create'])->name('create');
        Route::post('/', [BidangController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [BidangController::class, 'edit'])->name('edit');
        Route::delete('/{id}', [BidangController::class, 'destroy'])->name('destroy');
        Route::put('/{id}/ketua', [KetuaBidangController::class, 'update'])
            ->whereNumber('id')
            ->name('ketua.update');
        Route::put('/{id}/wakil-ketua', [WakilKetuaBidangController::class, 'update'])
            ->whereNumber('id')
            ->name('wakil-ketua.update');
    });

// Halaman profil admin (tambahan)
Route::view('/admin/profil', 'admin.profil')
    ->middleware(['auth', 'role:Admin', NoCache::class])
    ->name('admin.profil');

// Proses ubah kata sandi
Route::post('/ubah-kata-sandi', [ChangePasswordController::class, 'update'])
    ->middleware(['auth', NoCache::class])
    ->name('password.update');

// Layar wajib ganti kata sandi (setelah reset), untuk semua peran
Route::view('/ganti-kata-sandi', 'auth.ganti-kata-sandi')
    ->middleware(['auth', NoCache::class])
    ->name('password.edit');

// Kelola Akun
Route::middleware(['auth', 'role:Admin', NoCache::class])
    ->prefix('admin/akun')
    ->name('admin.akun.')
    ->group(function () {
        Route::get('/', [AkunController::class, 'index'])->name('index');
        Route::get('/tambah', [AkunController::class, 'create'])->name('create');
        Route::post('/', [AkunController::class, 'store'])->name('store');

        Route::get('/{id}/edit', [AkunController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('/{id}', [AkunController::class, 'update'])->whereNumber('id')->name('update');

        // Endpoint penetapan bidang
        Route::put('/{id}/bidang', [AkunController::class, 'updateBidang'])->whereNumber('id')->name('bidang.update');

        Route::delete('/{id}', [AkunController::class, 'destroy'])->whereNumber('id')->name('destroy');
        Route::post('/{id}/nonaktifkan', [AkunController::class, 'nonaktifkan'])->whereNumber('id')->name('nonaktifkan');
        Route::post('/{id}/aktifkan', [AkunController::class, 'aktifkan'])->whereNumber('id')->name('aktifkan');
        Route::get('/{id}/peran', [AkunController::class, 'editPeran'])->whereNumber('id')->name('peran.edit');
        Route::put('/{id}/peran', [AkunController::class, 'updatePeran'])->whereNumber('id')->name('peran.update');

        Route::post('/{id}/reset-password', [AkunController::class, 'resetPassword'])->whereNumber('id')->name('reset-password');

        // Endpoint alih admin
        Route::post('/alih-admin', [AkunController::class, 'alihAdmin'])->name('alih-admin');
    });

// Dashboard Penasihat
Route::middleware(['auth', 'role:Penasihat', NoCache::class])
    ->prefix('penasihat')
    ->name('penasihat.')
    ->group(function () {
        // Sementara: dasbor Penasihat belum ada, arahkan ke daftar program kerja.
        Route::get('/dashboard', fn() => redirect()->route('admin.program-kerja.index'))
            ->name('dashboard');
    });

// Program Kerja: tambah dan ubah (Pimpinan Bidang dan Anggota Bidang) - T-81, T-82, T-103, T-104
Route::middleware(['auth', PeranProgramKerja::middleware(PeranProgramKerja::KELOLA_PROGRAM), NoCache::class])
    ->prefix('program-kerja')
    ->name('program-kerja.')
    ->group(function () {
        Route::get('/tambah', [ProgramKerjaController::class, 'create'])->name('create');
        Route::get('/{id}/edit', [ProgramKerjaController::class, 'edit'])
            ->whereNumber('id')
            ->name('edit');
        Route::post('/', [ProgramKerjaController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '/{id}', [ProgramKerjaController::class, 'update'])
            ->whereNumber('id')
            ->name('update');
    });

// Kegiatan: daftar & tambah (hanya Anggota Bidang, ikut user story) - T-109, T-110
Route::middleware(['auth', PeranProgramKerja::middleware(PeranProgramKerja::KELOLA_KEGIATAN), NoCache::class])
    ->group(function () {
        // Halaman daftar kegiatan (dari sidebar)
        Route::get('/kegiatan', [KegiatanController::class, 'index'])
            ->name('kegiatan.index');

        // Jalur tambah dari halaman Daftar Kegiatan (pilih program kerja lewat dropdown)
        Route::get('/kegiatan/tambah', [KegiatanController::class, 'createDariSidebar'])
            ->name('kegiatan.create');
        Route::post('/kegiatan', [KegiatanController::class, 'storeDariSidebar'])
            ->name('kegiatan.store');

        // Jalur tambah dari baris tabel Program Kerja (program kerja terkunci)
        Route::prefix('program-kerja')
            ->name('program-kerja.')
            ->group(function () {
                Route::get('/{id}/kegiatan/tambah', [KegiatanController::class, 'create'])
                    ->whereNumber('id')
                    ->name('kegiatan.create');
                Route::post('/{id}/kegiatan', [KegiatanController::class, 'store'])
                    ->whereNumber('id')
                    ->name('kegiatan.store');
            });
    });

// Program kerja bidang lain (hanya baca, semua peran yang masuk) - T-88, T-89
Route::middleware(['auth', NoCache::class])
    ->prefix('program-kerja')
    ->name('program-kerja.')
    ->group(function () {
        Route::get('/bidang-lain', [ProgramKerjaController::class, 'bidangLain'])->name('bidang-lain');
    });

// Kelola Anggota (hanya Admin)
Route::middleware(['auth', 'role:Admin', NoCache::class])
    ->prefix('admin/anggota')
    ->name('admin.anggota.')
    ->group(function () {
        Route::get('/', [AnggotaController::class, 'index'])->name('index');
        Route::get('/tambah', [AnggotaController::class, 'create'])->name('create');
        Route::post('/', [AnggotaController::class, 'store'])->name('store');

        Route::get('/{id}/edit', [AnggotaController::class, 'edit'])->name('edit');
        Route::put('/{id}', [AnggotaController::class, 'update'])->name('update');

        Route::put('/{id}/pindah-bidang', [AnggotaController::class, 'pindahBidang'])
            ->name('pindah-bidang');
    });

// Daftar program kerja seluruh bidang (hanya baca; Admin dan Penasihat) - T-93
Route::middleware(['auth', 'role:Admin,Penasihat', NoCache::class])
    ->prefix('admin/program-kerja')
    ->name('admin.program-kerja.')
    ->group(function () {
        Route::get('/', [ProgramKerjaController::class, 'seluruhBidang'])
            ->name('index');
    });

// Daftar program kerja bidang sendiri (Pimpinan Bidang dan Anggota Bidang) - T-98
Route::middleware(['auth', PeranProgramKerja::middleware(PeranProgramKerja::LIHAT_BIDANG_SENDIRI), NoCache::class])
    ->prefix('program-kerja')
    ->name('program-kerja.')
    ->group(function () {
        Route::get('/bidang-saya', [ProgramKerjaController::class, 'bidangSaya'])
            ->name('bidang-saya');
    });

Route::middleware(['auth', AksesPersuratan::class, NoCache::class])
    ->prefix('surat')
    ->name('surat.')
    ->group(function () {
        Route::get('/', [PencarianSuratController::class, 'cari'])->name('index');
    });

// Daftar anggota bidang saya (Pimpinan Bidang dan Anggota Bidang) - T-52
Route::middleware(['auth', 'role:Pimpinan Bidang,Anggota Bidang', NoCache::class])
    ->prefix('anggota')
    ->name('anggota.')
    ->group(function () {
        Route::get('/bidang-saya', [AnggotaBidangController::class, 'index'])->name('bidang-saya');
    });

// Ubah data surat masuk/keluar (Admin dan Sekretariat) - B-038, T-69, T-70
Route::middleware(['auth', AksesPersuratan::class, NoCache::class])
    ->prefix('surat')
    ->name('surat.')
    ->group(function () {
        // Catat surat masuk (B-035, T-59)
        Route::get('/catat-masuk', [CatatSuratMasukController::class, 'create'])
            ->name('catat-masuk');

        Route::post('/catat-masuk', [CatatSuratMasukController::class, 'store'])
            ->name('simpan-masuk');
        Route::get('/{jenis}/{id}/edit', [UbahSuratController::class, 'edit'])
            ->whereIn('jenis', ['masuk', 'keluar'])
            ->whereNumber('id')
            ->name('edit');

        Route::put('/{jenis}/{id}', [UbahSuratController::class, 'update'])
            ->whereIn('jenis', ['masuk', 'keluar'])
            ->whereNumber('id')
            ->name('update');
    });

    // Pengujian error 500 khusus lingkungan lokal
if (app()->environment(['local', 'testing'])) {
    Route::get('/_test-error-500', function () {
        throw new \RuntimeException('RAHASIA_UJI_ERROR_500');
    });
}

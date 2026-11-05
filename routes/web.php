<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Admin\BidangController;
use App\Http\Controllers\Admin\AkunController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\NoCache;

// Halaman utama (publik)
Route::view('/', 'landing')->name('beranda');

// Halaman login
Route::view('/login', 'auth.login')
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
    });

// Halaman profil admin (tambahan)
Route::view('/admin/profil', 'admin.profil')
    ->middleware(['auth', 'role:Admin', NoCache::class])
    ->name('admin.profil');

// Proses ubah kata sandi
Route::post('/ubah-kata-sandi', [ChangePasswordController::class, 'update'])
    ->middleware('auth')
    ->name('password.update');

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
    });
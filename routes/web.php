<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// Halaman utama (publik)
Route::view('/', 'landing')->name('beranda');

// Halaman login
Route::view('/login', 'auth.login')
    ->middleware('guest')
    ->name('login');

// Proses login
Route::post('/login', [LoginController::class, 'login'])
    ->middleware(['guest', 'throttle:login'])
    ->name('login.process');

// Logout
Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Dashboard admin
Route::view('/admin/dashboard', 'admin.dashboard')
    ->middleware('auth')
    ->name('admin.dashboard');

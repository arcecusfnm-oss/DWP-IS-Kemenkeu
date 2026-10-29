<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// Halaman utama
Route::view('/', 'landing')->name('beranda');

// Halaman login
Route::view('/login', 'auth.login')->name('login');

// Proses login
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:login')
    ->name('login.process');

// Logout
Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout');

// Dashboard admin
Route::view('/admin/dashboard', 'admin.dashboard')
    ->name('admin.dashboard');

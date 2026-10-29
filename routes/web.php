<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// T-11: Halaman masuk (tampilan).
Route::view('/login', 'auth.login')->name('login');

// T-10: Proses autentikasi login.
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:login')
    ->name('login.process');

// Logout.
Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout');

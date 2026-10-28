<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// T-11: halaman masuk (tampilan). Proses POST /login dibuat di T-10.
Route::view('/login', 'auth.login')->name('login');
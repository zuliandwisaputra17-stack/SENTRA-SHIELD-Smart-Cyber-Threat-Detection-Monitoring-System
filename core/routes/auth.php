<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
| Hanya login. Pengguna dibuat oleh Admin (tidak ada registrasi publik), dan
| reset kata sandi lewat email tidak dipakai karena sistem berjalan offline.
*/
Route::middleware('guest')->group(function () {
    Volt::route('login', 'pages.auth.login')
        ->name('login');
});

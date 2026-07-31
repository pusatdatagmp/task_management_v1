<?php

/**
 * ==========================================================
 * MODUL       : routes/auth.php
 * KLASIFIKASI : CONFIG
 * TUJUAN      : Auth Hari-1 hanya login/logout (PROMPT-HARI-1 §F). Register,
 *               forgot/reset-password, dan email verification DIMATIKAN — akun
 *               dibuat admin lewat seeder/CRUD user (Hari-2), bukan self-signup.
 * DIPANGGIL   : bootstrap/app.php (require routes/web.php -> routes/auth.php)
 * MEMANGGIL   : AuthenticatedSessionController, ConfirmablePasswordController
 * DATA MASUK  : Form login (email/password)
 * DATA KELUAR : Sesi login, redirect ke dashboard
 * RISIKO      : Controller register/reset-password/verify-email SENGAJA tidak
 *               dihapus (masih ada di app/Http/Controllers/Auth) supaya tidak
 *               menyentuh file yang tidak diminta — cukup rute-nya yang dicabut
 *               di sini sehingga tidak bisa diakses.
 * ==========================================================
 */

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

// Settings personal (Profile/Password/Appearance) SEKARANG hanya lewat modal
// (SettingsModal, dipicu dari dropdown user) -- permintaan Boss, halaman penuh
// DIPENSIUNKAN total (GET profile.edit/password.edit/appearance dihapus, bukan
// cuma tak dipakai). Endpoint MUTASI tetap sama persis, form modal reuse ini.
// Appearance TIDAK punya endpoint sama sekali -- preferensi light/dark/system
// murni client-side (localStorage, lihat hooks/use-appearance.tsx), tidak pernah
// menyentuh backend.
Route::middleware('auth')->group(function () {
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');
});

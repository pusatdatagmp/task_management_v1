<?php

/**
 * ==========================================================
 * MODUL       : ProfileUpdateTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi alur update profil & hapus akun sendiri, termasuk
 *               F-16 (soft delete — baris user tetap ada demi data KPI).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : User::factory(), route /settings/profile
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau test 'user can delete their account' lolos padahal baris
 *               ter-hard-delete, itu bug F-16 (data KPI hilang) yang lolos tanpa terdeteksi.
 * ==========================================================
 */

use App\Models\User;

// F-78: DEVIASI SADAR -- 'profile page is displayed' DIHAPUS (bukan diperbarui
// jadi lulus paksa). Halaman penuh /settings/profile DIPENSIUNKAN (permintaan
// Boss, Settings sekarang SettingsModal client-side) -- "halaman ditampilkan"
// bukan lagi konsep yang valid untuk di-GET, dan render modal tidak bisa
// dibuktikan lewat test HTTP (F-75, butuh mata manusia/browser). Cakupan
// "profil bisa dibaca/diedit" tetap terjaga oleh test PATCH di bawah.
//
// F-78: DEVIASI SADAR KE-2 -- 'profile information can be updated' (default
// User::factory() = role member, F-90) SEBELUMNYA expect email BERHASIL
// diganti. Permintaan Boss: user biasa (tanpa user.manage) TIDAK BOLEH ganti
// email sendiri. Dipecah jadi 3 test di bawah supaya kedua sisi (member
// ditolak, admin/user.manage lolos) sama-sama tercakup eksplisit -- cakupan
// SETARA (nama tetap teruji, email member-vs-admin ditambah), bukan menyusut.

test('a member can update their own name', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/dashboard/overview')
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/dashboard/overview');

    expect($user->refresh()->name)->toBe('Test User');
});

test('a member (without user.manage) cannot change their own email, even submitted directly (F-90, server-enforced)', function () {
    $user = User::factory()->create();
    $originalEmail = $user->email;

    $response = $this
        ->actingAs($user)
        ->from('/dashboard/overview')
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => 'hacked@example.com',
        ]);

    // SUMBER: TIDAK ada error validasi -- field email DIBUANG diam-diam
    // (bukan ditolak dengan pesan), request tetap sukses untuk field lain (name).
    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/dashboard/overview');

    $user->refresh();
    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe($originalEmail)
        ->and($user->email_verified_at)->not->toBeNull();
});

test('an admin (user.manage) CAN change their own email', function () {
    $admin = User::factory()->admin()->create();

    $response = $this
        ->actingAs($admin)
        ->from('/dashboard/overview')
        ->patch('/settings/profile', [
            'name' => $admin->name,
            'email' => 'admin-new@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/dashboard/overview');

    $admin->refresh();
    expect($admin->email)->toBe('admin-new@example.com')
        ->and($admin->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/dashboard/overview')
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/dashboard/overview');

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/settings/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    // F-16: users pakai soft delete — baris tetap ada (data KPI), deleted_at terisi.
    expect($user->fresh()->trashed())->toBeTrue();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/dashboard/overview')
        ->delete('/settings/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/dashboard/overview');

    expect($user->fresh())->not->toBeNull();
});

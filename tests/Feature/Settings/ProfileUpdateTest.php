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

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/settings/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

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
        ->from('/settings/profile')
        ->delete('/settings/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/settings/profile');

    expect($user->fresh())->not->toBeNull();
});

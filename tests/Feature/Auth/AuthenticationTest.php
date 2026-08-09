<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

// DEVIASI Hari-5 (§D5): redirect landing SEKARANG beda per role (03-BUSINESS-FLOW
// §7 — admin ke Dashboard, member ke My Tasks, spec ini sudah ada sejak Hari-1
// tapi baru diimplementasikan sekarang). Test lama mengasumsikan semua role landing
// ke dashboard — DIGANTI jadi dua test eksplisit per role, bukan dihapus.
test('a member authenticating from the login screen lands on My Tasks', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('tasks.my', absolute: false));
});

// BUG FIX (permintaan Boss 2026-08-07): landing admin dulu ke 'dashboard'
// (dashboard 3-angka LAMA) -- sekarang ke 'dashboard.overview' (Command
// Center), konsisten dengan nav sidebar yang sudah pindah sejak v1.2 H4
// (app-sidebar.tsx:58). Perilaku SENGAJA diubah, assertion diperbarui, bukan
// ditambal (F-78) -- route 'dashboard' (lama) sendiri TETAP ada & jalan,
// cuma bukan lagi TUJUAN landing setelah login.
test('an admin authenticating from the login screen lands on Command Center', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard.overview', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

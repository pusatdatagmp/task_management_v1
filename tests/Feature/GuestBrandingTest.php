<?php

/**
 * ==========================================================
 * MODUL       : GuestBrandingTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi F-169 (audit Boss 2026-08-10) — guest (belum login)
 *               tetap melihat branding+tema organisasi (bukan fallback "TEMPO"
 *               keras) karena proyek ini single-tenant sampai v3.0. Guard nol-
 *               organisasi (instalasi belum diseed) tetap aman, tidak crash.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : HandleInertiaRequests::share()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau fallback ini salah pasang, guest bisa lihat branding
 *               organisasi yang SALAH begitu multi-tenant v3.0 hidup (lihat
 *               komentar F-169 di middleware -- WAJIB direvisit saat itu).
 * ==========================================================
 */

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

test('guest (belum login) tetap melihat branding organisasi tunggal, bukan fallback TEMPO (F-169)', function () {
    $organization = Organization::create([
        'name' => 'Guest Branding Test Org',
        'slug' => 'guest-branding-test-org',
        'company_name' => 'PT Contoh Boss',
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($p) => $p->where('branding.company_name', 'PT Contoh Boss'));

    // Guard: hasil ini murni ketergantungan pada baris create() di atas, bukan
    // organisasi lain yang kebetulan ada di DB test -- pastikan ini benar SATU-
    // SATUNYA organisasi di DB test ini (RefreshDatabase per test).
    expect(Organization::count())->toBe(1)->and($organization->id)->not->toBeNull();
});

test('guest tetap melihat tema kustom organisasi tunggal, bukan default TEMPO (F-169)', function () {
    Organization::create([
        'name' => 'Guest Theme Test Org',
        'slug' => 'guest-theme-test-org',
        'theme_config' => ['tokens' => ['amber' => '#123abc'], 'gradient' => null],
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($p) => $p->where('theme.tokens.amber', '#123abc'));
});

test('nol organisasi di DB (belum diseed) -- guest tetap dapat branding/theme null, TIDAK crash (F-169 guard)', function () {
    expect(Organization::count())->toBe(0);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($p) => $p->where('branding', null)->where('theme', null));
});

test('user login TETAP pakai organisasi sendiri, bukan Organization::first() (F-5/F-169 -- tak pernah "nebak" utk user dikenal)', function () {
    $orgA = Organization::create(['name' => 'Org A', 'slug' => 'org-a', 'company_name' => 'Org A Branding']);
    $orgB = Organization::create(['name' => 'Org B', 'slug' => 'org-b', 'company_name' => 'Org B Branding']);

    $roles = RolePermissionSeeder::seedSystemRolesForOrganization($orgB);
    $userInOrgB = User::factory()->create([
        'organization_id' => $orgB->id,
        'role_id' => $roles['admin']->id,
    ]);

    // $orgA dibuat LEBIH DULU (id lebih kecil) -- kalau guard salah pakai
    // Organization::first() untuk user LOGIN, ini akan bocor branding Org A
    // ke user Org B (F-5 pelanggaran org-scope).
    $response = $this->actingAs($userInOrgB)->get(route('tasks.my'));

    $response->assertOk();
    $response->assertInertia(fn ($p) => $p->where('branding.company_name', 'Org B Branding'));

    expect($orgA->id)->toBeLessThan($orgB->id);
});

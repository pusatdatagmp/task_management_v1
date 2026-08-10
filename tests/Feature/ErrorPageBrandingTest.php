<?php

/**
 * ==========================================================
 * MODUL       : ErrorPageBrandingTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi F-169 (audit Boss 2026-08-10) — halaman error
 *               403/404/500 kustom (bootstrap/app.php withExceptions()) TETAP
 *               membawa branding/tema organisasi walau route SAMA SEKALI TIDAK
 *               match. Root cause: NotFoundHttpException murni dilempar
 *               Router::findRoute() SEBELUM pipeline middleware (termasuk
 *               HandleInertiaRequests) sempat jalan -- Inertia::share() kosong
 *               tanpa fix ini, AppLogo/tema frontend fallback ke default TEMPO.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : bootstrap/app.php withExceptions()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Regresi di sini = halaman error kembali tampil branding salah
 *               (TEMPO generik) tiap kali user salah ketik URL -- persis
 *               keluhan Boss yang memicu audit ini.
 * ==========================================================
 */

use App\Models\Organization;
use App\Models\User;

test('404 murni (route tak pernah terdaftar) TETAP membawa branding+tema organisasi (F-169)', function () {
    Organization::create([
        'name' => 'Error Page Branding Org',
        'slug' => 'error-page-branding-org',
        'company_name' => 'PT Error Page Test',
        'theme_config' => ['tokens' => ['amber' => '#654321'], 'gradient' => null],
    ]);

    $response = $this->get('/route-yang-benar-benar-tidak-pernah-terdaftar-di-manapun');

    $response->assertNotFound();
    $response->assertInertia(fn ($p) => $p
        ->component('errors/error')
        ->where('status', 404)
        ->where('branding.company_name', 'PT Error Page Test')
        ->where('theme.tokens.amber', '#654321'));
});

test('403 (route match, gate menolak) tetap membawa branding organisasi (guard regresi, F-169)', function () {
    $organization = Organization::create([
        'name' => 'Error 403 Branding Org',
        'slug' => 'error-403-branding-org',
        'company_name' => 'PT Forbidden Test',
    ]);
    $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    // leaderboard.view = default_admin false (F-134) -- admin biasa 403 di sini.
    $response = $this->actingAs($admin)->get(route('leaderboard.index'));

    $response->assertForbidden();
    $response->assertInertia(fn ($p) => $p
        ->component('errors/error')
        ->where('status', 403)
        ->where('branding.company_name', 'PT Forbidden Test'));

    // Guard nol-pakai variabel -- pastikan $member benar-benar dibuat di org
    // yang sama (setup valid), bukan longgar/asal.
    expect($member->organization_id)->toBe($admin->organization_id);
});

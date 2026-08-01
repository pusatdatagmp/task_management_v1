<?php

/**
 * ==========================================================
 * MODUL       : ThemeSettingsTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi tab Tema halaman Setelan (F-143/F-144, v1.2 DS-3) —
 *               org-scoped (F-5), fallback default TEMPO (F-145) saat org belum
 *               kustom, validasi hex/gradient ketat (cegah CSS injection), gating
 *               settings.manage (reuse DS-2, F-90).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : SettingsController::updateTheme(), UpdateThemeRequest
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau validasi hex/direction longgar, nilai bebas dari admin bisa
 *               ter-inject ke CSS custom property (linear-gradient(...) dibangun
 *               dari input mentah) dan berpotensi merusak/menyuntik style app-wide.
 * ==========================================================
 */

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

test('a user without settings.manage gets 403 on theme update', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $response = $this->actingAs($member)->post(route('settings.theme.update'), ['tokens' => ['amber' => '#123456']]);

    $response->assertForbidden();
});

test('a user with settings.manage can save theme tokens, org-scoped (F-5)', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('settings.theme.update'), [
        'tokens' => [
            'sidebar_bg' => '#111111',
            'ink' => '#222222',
            'amber' => '#e0a012',
        ],
    ]);

    $response->assertRedirect();

    $admin->organization->refresh();
    expect($admin->organization->theme_config['tokens']['sidebar_bg'])->toBe('#111111')
        ->and($admin->organization->theme_config['tokens']['ink'])->toBe('#222222')
        ->and($admin->organization->theme_config['tokens']['amber'])->toBe('#e0a012');
});

test('theme never leaks across organizations (F-5/F-15)', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminA)->post(route('settings.theme.update'), ['tokens' => ['amber' => '#aaaaaa']]);
    $this->actingAs($adminB)->post(route('settings.theme.update'), ['tokens' => ['amber' => '#bbbbbb']]);

    expect($adminA->organization->fresh()->theme_config['tokens']['amber'])->toBe('#aaaaaa')
        ->and($adminB->organization->fresh()->theme_config['tokens']['amber'])->toBe('#bbbbbb');
});

test('an org with no theme_config falls back to default TEMPO (null, not a crash)', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('settings.index'));

    $response->assertInertia(fn ($p) => $p->where('theme', null));

    // F-143: dishare GLOBAL juga, bukan cuma halaman Setelan.
    $anyPage = $this->actingAs($admin)->get(route('tasks.my'));
    $anyPage->assertInertia(fn ($p) => $p->where('theme', null));
});

test('an invalid (non-hex) token color is rejected', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('settings.theme.update'), [
        'tokens' => ['amber' => 'javascript:alert(1)'],
    ]);

    $response->assertSessionHasErrors('tokens.amber');
    expect($admin->organization->fresh()->theme_config)->toBeNull();
});

test('a token key outside the whitelist is silently ignored, never persisted (F-144 -- token only, not arbitrary CSS)', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('settings.theme.update'), [
        'tokens' => ['amber' => '#e0a012', 'emerald' => '#0ea371'],
    ]);

    $response->assertRedirect();

    $tokens = $admin->organization->fresh()->theme_config['tokens'];
    expect($tokens)->toHaveKey('amber')
        ->and($tokens)->not->toHaveKey('emerald');
});

test('gradient requires valid from/to hex when enabled, and an invalid direction is rejected', function () {
    $admin = User::factory()->admin()->create();

    $missingColors = $this->actingAs($admin)->post(route('settings.theme.update'), [
        'gradient' => ['enabled' => true],
    ]);
    $missingColors->assertSessionHasErrors(['gradient.from', 'gradient.to']);

    $badDirection = $this->actingAs($admin)->post(route('settings.theme.update'), [
        'gradient' => ['enabled' => true, 'from' => '#e0a012', 'to' => '#161d30', 'direction' => 'body{display:none}'],
    ]);
    $badDirection->assertSessionHasErrors('gradient.direction');
});

test('a valid enabled gradient is saved and a disabled gradient is stored as null', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('settings.theme.update'), [
        'gradient' => ['enabled' => true, 'from' => '#e0a012', 'to' => '#161d30', 'direction' => 'to bottom'],
    ])->assertRedirect();

    $admin->organization->refresh();
    // SUMBER: assert per-key (bukan toBe() array utuh) -- kolom JSON MySQL
    // TIDAK menjamin urutan key survive round-trip, cuma isinya yang harus benar.
    expect($admin->organization->theme_config['gradient'])
        ->toMatchArray(['enabled' => true, 'from' => '#e0a012', 'to' => '#161d30', 'direction' => 'to bottom']);

    $this->actingAs($admin)->post(route('settings.theme.update'), [
        'gradient' => ['enabled' => false],
    ])->assertRedirect();

    expect($admin->organization->fresh()->theme_config['gradient'])->toBeNull();
});

test('settings.manage (shared with Branding DS-2) is reused, no new permission created for theme', function () {
    $names = collect(RolePermissionSeeder::catalog())->pluck('permission_name');

    expect($names)->toHaveCount($names->unique()->count())
        ->and($names)->toContain('settings.manage');
});

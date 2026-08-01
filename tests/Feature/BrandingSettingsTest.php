<?php

/**
 * ==========================================================
 * MODUL       : BrandingSettingsTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi halaman "Setelan" tab Branding (F-142, v1.2 DS-2) —
 *               org-scoped (F-5), gating permission settings.manage BARU (F-90,
 *               default_admin TRUE), upload logo tervalidasi (pola F-104/105),
 *               logo lama dihapus fisik saat diganti, branding dishare GLOBAL
 *               (HandleInertiaRequests) untuk sidebar dinamis (F-142).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : SettingsController, UpdateBrandingRequest, Organization::logoUrl()
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau gating settings.manage bocor, admin custom tanpa izin bisa
 *               ganti identitas perusahaan org lain (kalau IDOR) atau org sendiri
 *               tanpa otorisasi eksplisit.
 * ==========================================================
 */

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('admin (default_admin=TRUE) automatically has settings.manage', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can('settings.manage'))->toBeTrue();
});

test('a user without settings.manage gets 403 on Setelan page and update', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($member)->get(route('settings.index'))->assertForbidden();
    $this->actingAs($member)->post(route('settings.branding.update'), ['company_name' => 'X'])->assertForbidden();
});

test('a user with settings.manage can view and update branding, org-scoped (F-5)', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('settings.branding.update'), [
        'company_name' => 'Deevatech Nusantara',
        'address' => 'Jl. Contoh No. 1',
        'wa_number' => '628123456789',
        'facebook_url' => 'https://facebook.com/deevatech',
        'instagram_url' => 'https://instagram.com/deevatech',
        'linkedin_url' => 'https://linkedin.com/company/deevatech',
    ]);

    $response->assertRedirect();

    $admin->organization->refresh();
    expect($admin->organization->company_name)->toBe('Deevatech Nusantara')
        ->and($admin->organization->address)->toBe('Jl. Contoh No. 1')
        ->and($admin->organization->wa_number)->toBe('628123456789')
        ->and($admin->organization->facebook_url)->toBe('https://facebook.com/deevatech');

    $page = $this->actingAs($admin)->get(route('settings.index'));
    $page->assertInertia(fn ($p) => $p
        ->component('org-settings/index')
        ->where('branding.company_name', 'Deevatech Nusantara')
        ->where('branding.address', 'Jl. Contoh No. 1'));
});

test('branding never leaks across organizations (F-5/F-15)', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminA)->post(route('settings.branding.update'), ['company_name' => 'Org A']);
    $this->actingAs($adminB)->post(route('settings.branding.update'), ['company_name' => 'Org B']);

    $adminA->organization->refresh();
    $adminB->organization->refresh();

    expect($adminA->organization->company_name)->toBe('Org A')
        ->and($adminB->organization->company_name)->toBe('Org B');

    $pageA = $this->actingAs($adminA)->get(route('settings.index'));
    $pageA->assertInertia(fn ($p) => $p->where('branding.company_name', 'Org A'));
});

test('a valid image logo upload is accepted, stored on public disk, and old file removed on replace', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $first = UploadedFile::fake()->image('logo.png', 200, 200)->size(500);
    $this->actingAs($admin)->post(route('settings.branding.update'), ['logo' => $first])->assertRedirect();

    $admin->organization->refresh();
    $firstPath = $admin->organization->logo_path;
    expect($firstPath)->not->toBeNull();
    Storage::disk('public')->assertExists($firstPath);

    $second = UploadedFile::fake()->image('logo2.png', 200, 200)->size(500);
    $this->actingAs($admin)->post(route('settings.branding.update'), ['logo' => $second])->assertRedirect();

    $admin->organization->refresh();
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($admin->organization->logo_path);
});

test('a non-image file is rejected as logo (F-104/105-style mimes validation)', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

    $response = $this->actingAs($admin)->post(route('settings.branding.update'), ['logo' => $file]);

    $response->assertSessionHasErrors('logo');
    expect($admin->organization->fresh()->logo_path)->toBeNull();
});

test('an svg logo is rejected (XSS surface, not in allowed mimes)', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $file = UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml');

    $response = $this->actingAs($admin)->post(route('settings.branding.update'), ['logo' => $file]);

    $response->assertSessionHasErrors('logo');
});

test('branding is shared globally for the sidebar, fields null when the org has not filled it in yet', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('tasks.my'));

    $response->assertInertia(fn ($p) => $p
        ->where('branding.company_name', null)
        ->where('branding.logo_url', null));
});

test('an invalid wa_number format is rejected', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('settings.branding.update'), ['wa_number' => 'bukan-nomor']);

    $response->assertSessionHasErrors('wa_number');
});

test('settings.manage appears in the permission catalog with default_admin true', function () {
    $catalogEntry = collect(RolePermissionSeeder::catalog())->firstWhere('permission_name', 'settings.manage');

    expect($catalogEntry)->not->toBeNull()
        ->and($catalogEntry['default_admin'] ?? true)->toBeTrue();
});

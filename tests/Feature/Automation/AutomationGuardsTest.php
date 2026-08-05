<?php

/**
 * ==========================================================
 * MODUL       : AutomationGuardsTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : G1 (prompt AE-2) — tiap Guard komposabel (F-158/161) diuji SENDIRI,
 *               pass & skip, lepas dari Pipeline/Command penuh.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : ActiveTemplateGuard, TimeDeltaGuard, DateWindowGuard, QuotaGuard
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Guard SALAH urutan pass/skip = seluruh rantai Pipeline salah
 *               (F-158 "skip pertama menghentikan") — pagar per-unit di sini.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\Automation\AutomationContext;
use App\Services\Automation\Guards\ActiveTemplateGuard;
use App\Services\Automation\Guards\DateWindowGuard;
use App\Services\Automation\Guards\QuotaGuard;
use App\Services\Automation\Guards\TimeDeltaGuard;
use Carbon\Carbon;

function createGuardTestTemplate(array $overrides = []): TaskTemplate
{
    $admin = User::factory()->admin()->create();
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Guard Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);
    TaskStatus::seedDefaults($project);

    return TaskTemplate::create(array_merge([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Guard Test Template '.uniqid(),
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => [],
        'is_active' => true,
        'anchor_strategy' => 'time_based',
        'interval_value' => 1,
        'interval_unit' => 'day',
    ], $overrides));
}

function ctxAt(Carbon $nowWib, array $activeInstanceCounts = [], array $latestTaskByTemplateId = []): AutomationContext
{
    return new AutomationContext($nowWib, collect(), collect(), $latestTaskByTemplateId, $activeInstanceCounts);
}

// ---------------------------------------------------------------------------
// ActiveTemplateGuard
// ---------------------------------------------------------------------------

test('ActiveTemplateGuard: Pass kalau is_active=true', function () {
    $template = createGuardTestTemplate(['is_active' => true]);
    $decision = (new ActiveTemplateGuard)->check($template, ctxAt(Carbon::create(2026, 8, 3)));
    expect($decision)->toBeNull();
});

test('ActiveTemplateGuard: Skip kalau is_active=false', function () {
    $template = createGuardTestTemplate(['is_active' => false]);
    $decision = (new ActiveTemplateGuard)->check($template, ctxAt(Carbon::create(2026, 8, 3)));
    expect($decision)->not->toBeNull();
    expect($decision->action)->toBe('skip');
    expect($decision->reason)->toBe('template-tidak-aktif');
});

// ---------------------------------------------------------------------------
// TimeDeltaGuard
// ---------------------------------------------------------------------------

test('TimeDeltaGuard: Pass kalau last_generated_date belum pernah diisi (null)', function () {
    $template = createGuardTestTemplate(['interval_value' => 7, 'interval_unit' => 'day']);
    $decision = (new TimeDeltaGuard)->check($template, ctxAt(Carbon::create(2026, 8, 3)));
    expect($decision)->toBeNull();
});

test('TimeDeltaGuard: Skip belum-waktunya kalau interval belum tercapai', function () {
    $template = createGuardTestTemplate(['interval_value' => 7, 'interval_unit' => 'day']);
    $template->update(['last_generated_date' => '2026-08-01']);

    // Baru 2 hari berlalu dari 7 hari yang disyaratkan.
    $decision = (new TimeDeltaGuard)->check($template->refresh(), ctxAt(Carbon::create(2026, 8, 3)));

    expect($decision)->not->toBeNull();
    expect($decision->reason)->toBe('belum-waktunya');
});

test('TimeDeltaGuard: Pass kalau interval sudah tercapai', function () {
    $template = createGuardTestTemplate(['interval_value' => 7, 'interval_unit' => 'day']);
    $template->update(['last_generated_date' => '2026-08-01']);

    $decision = (new TimeDeltaGuard)->check($template->refresh(), ctxAt(Carbon::create(2026, 8, 8)));

    expect($decision)->toBeNull();
});

test('TimeDeltaGuard: Pass kalau interval_unit NULL walau sudah pernah generate (AE-2b, calendar_anchored tanpa interval)', function () {
    $template = createGuardTestTemplate(['interval_value' => null, 'interval_unit' => null, 'anchor_strategy' => 'calendar_anchored']);
    $template->update(['last_generated_date' => '2026-08-03']); // "kemarin", tanpa interval tetap Pass

    $decision = (new TimeDeltaGuard)->check($template->refresh(), ctxAt(Carbon::create(2026, 8, 4)));

    expect($decision)->toBeNull();
});

// ---------------------------------------------------------------------------
// DateWindowGuard
// ---------------------------------------------------------------------------

test('DateWindowGuard: Pass kalau date_window_config kosong (tak ada batasan)', function () {
    $template = createGuardTestTemplate(['date_window_config' => null]);
    $decision = (new DateWindowGuard)->check($template, ctxAt(Carbon::create(2026, 8, 8))); // Sabtu
    expect($decision)->toBeNull();
});

test('DateWindowGuard: Skip kalau hari ini di luar weekdays yang diizinkan', function () {
    $template = createGuardTestTemplate(['date_window_config' => ['weekdays' => [1, 2, 3, 4, 5]]]);
    $decision = (new DateWindowGuard)->check($template, ctxAt(Carbon::create(2026, 8, 8))); // Sabtu = 6
    expect($decision)->not->toBeNull();
    expect($decision->reason)->toBe('di-luar-jendela-tanggal');
});

test('DateWindowGuard: Pass kalau hari ini masuk weekdays yang diizinkan', function () {
    $template = createGuardTestTemplate(['date_window_config' => ['weekdays' => [1, 2, 3, 4, 5]]]);
    $decision = (new DateWindowGuard)->check($template, ctxAt(Carbon::create(2026, 8, 3))); // Senin = 1
    expect($decision)->toBeNull();
});

test('DateWindowGuard: Skip kalau tanggal di luar rentang dom_min-dom_max', function () {
    $template = createGuardTestTemplate(['date_window_config' => ['dom_min' => 1, 'dom_max' => 25]]);
    $decision = (new DateWindowGuard)->check($template, ctxAt(Carbon::create(2026, 8, 28)));
    expect($decision)->not->toBeNull();
    expect($decision->reason)->toBe('di-luar-jendela-tanggal');
});

// ---------------------------------------------------------------------------
// QuotaGuard
// ---------------------------------------------------------------------------

test('QuotaGuard: Pass kalau max_active_instances null (tak terbatas)', function () {
    $template = createGuardTestTemplate(['max_active_instances' => null]);
    $decision = (new QuotaGuard)->check($template, ctxAt(Carbon::create(2026, 8, 3), [$template->id => 999]));
    expect($decision)->toBeNull();
});

test('QuotaGuard: Skip kuota-penuh kalau active_count >= max', function () {
    $template = createGuardTestTemplate(['max_active_instances' => 3]);
    $decision = (new QuotaGuard)->check($template, ctxAt(Carbon::create(2026, 8, 3), [$template->id => 3]));
    expect($decision)->not->toBeNull();
    expect($decision->reason)->toBe('kuota-penuh');
});

test('QuotaGuard: Pass kalau active_count di bawah max', function () {
    $template = createGuardTestTemplate(['max_active_instances' => 3]);
    $decision = (new QuotaGuard)->check($template, ctxAt(Carbon::create(2026, 8, 3), [$template->id => 2]));
    expect($decision)->toBeNull();
});

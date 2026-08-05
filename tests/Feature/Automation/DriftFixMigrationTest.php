<?php

/**
 * ==========================================================
 * MODUL       : DriftFixMigrationTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : D1 (prompt AE-2b) — buktikan migrasi korektif F-163 benar dengan
 *               ANGKA: weekly/monthly -> calendar_anchored (anchor_config diambil
 *               dari recurrence_config), daily TETAP time_based, idempotent
 *               (jalan 2x hasil identik, tidak menimpa pilihan manual Boss).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : migration 2026_08_05_141832_remap_legacy_weekly_monthly_to_calendar_anchored_strategy
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Pagar F-163 — kalau migrasi ini regresi (mis. daily ikut
 *               ter-remap, atau anchor_config salah ambil field), drift yang
 *               sudah diperbaiki bisa muncul lagi atau template daily rusak.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;

function createDriftFixProject(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Drift Fix Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);
    TaskStatus::seedDefaults($project);

    return $project;
}

test('D1: weekly time_based (legacy) -> calendar_anchored day_of_week dari recurrence_config (bukti angka)', function () {
    $admin = User::factory()->admin()->create();
    $project = createDriftFixProject($admin);

    // Simulasikan state SETELAH migrasi AE-1 (F-159 poin 4): anchor_strategy
    // sudah 'time_based' (default migrasi lama), recurrence_config legacy
    // day_of_week=3 (Rabu) MASIH ada (F-121, tidak pernah dihapus).
    $weekly = TaskTemplate::create([
        'organization_id' => $admin->organization_id, 'project_id' => $project->id,
        'title' => 'Legacy Weekly Rabu', 'task_type' => 'weekly', 'estimated_minutes' => 60,
        'points' => 10, 'priority' => 'normal', 'recurrence_config' => ['day_of_week' => 3],
        'default_assignees' => [], 'anchor_strategy' => 'time_based',
        'interval_value' => 1, 'interval_unit' => 'week',
    ]);

    $migration = require database_path('migrations/2026_08_05_141832_remap_legacy_weekly_monthly_to_calendar_anchored_strategy.php');
    $migration->up();

    $weekly->refresh();
    expect($weekly->anchor_strategy)->toBe('calendar_anchored');
    expect($weekly->anchor_config)->toBe(['day_of_week' => 3]);
    // interval_value/unit TIDAK diubah -- tetap dari migrasi AE-1 lama.
    expect($weekly->interval_value)->toBe(1);
    expect($weekly->interval_unit)->toBe('week');
});

test('D1: monthly time_based (legacy) -> calendar_anchored day_of_month dari recurrence_config (bukti angka)', function () {
    $admin = User::factory()->admin()->create();
    $project = createDriftFixProject($admin);

    $monthly = TaskTemplate::create([
        'organization_id' => $admin->organization_id, 'project_id' => $project->id,
        'title' => 'Legacy Monthly Tgl 15', 'task_type' => 'monthly', 'estimated_minutes' => 90,
        'points' => 15, 'priority' => 'normal', 'recurrence_config' => ['day_of_month' => 15],
        'default_assignees' => [], 'anchor_strategy' => 'time_based',
        'interval_value' => 1, 'interval_unit' => 'month',
    ]);

    $migration = require database_path('migrations/2026_08_05_141832_remap_legacy_weekly_monthly_to_calendar_anchored_strategy.php');
    $migration->up();

    $monthly->refresh();
    expect($monthly->anchor_strategy)->toBe('calendar_anchored');
    expect($monthly->anchor_config)->toBe(['day_of_month' => 15]);
});

test('D1: daily TETAP time_based, TIDAK ikut ter-remap (F-163 -- nol drift, nol hari-tetap)', function () {
    $admin = User::factory()->admin()->create();
    $project = createDriftFixProject($admin);

    $daily = TaskTemplate::create([
        'organization_id' => $admin->organization_id, 'project_id' => $project->id,
        'title' => 'Legacy Daily', 'task_type' => 'daily', 'estimated_minutes' => 30,
        'points' => 5, 'priority' => 'normal', 'recurrence_config' => [],
        'default_assignees' => [], 'anchor_strategy' => 'time_based',
        'interval_value' => 1, 'interval_unit' => 'day',
    ]);

    $migration = require database_path('migrations/2026_08_05_141832_remap_legacy_weekly_monthly_to_calendar_anchored_strategy.php');
    $migration->up();

    expect($daily->refresh()->anchor_strategy)->toBe('time_based');
    expect($daily->anchor_config)->toBeNull();
});

test('D1: idempotent -- jalan 2x TIDAK menimpa pilihan manual Boss (anchor_strategy sudah bukan time_based)', function () {
    $admin = User::factory()->admin()->create();
    $project = createDriftFixProject($admin);

    $weekly = TaskTemplate::create([
        'organization_id' => $admin->organization_id, 'project_id' => $project->id,
        'title' => 'Legacy Weekly', 'task_type' => 'weekly', 'estimated_minutes' => 60,
        'points' => 10, 'priority' => 'normal', 'recurrence_config' => ['day_of_week' => 2],
        'default_assignees' => [], 'anchor_strategy' => 'time_based',
        'interval_value' => 1, 'interval_unit' => 'week',
    ]);

    $migration = require database_path('migrations/2026_08_05_141832_remap_legacy_weekly_monthly_to_calendar_anchored_strategy.php');
    $migration->up();

    // Boss SENGAJA ubah manual lewat form (FASE C) jadi completion_based dengan
    // anchor_config day_of_week BEDA dari recurrence_config lama.
    $weekly->refresh()->update(['anchor_strategy' => 'completion_based', 'anchor_config' => ['day_of_week' => 5]]);

    // Migrasi dijalankan LAGI (mis. deploy ulang) -- filter anchor_strategy=
    // 'time_based' membuat baris ini TIDAK ketemu lagi -> pilihan Boss AMAN.
    $migration->up();

    expect($weekly->refresh()->anchor_strategy)->toBe('completion_based');
    expect($weekly->anchor_config)->toBe(['day_of_week' => 5]);
});

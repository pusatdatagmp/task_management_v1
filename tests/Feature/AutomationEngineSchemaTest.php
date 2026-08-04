<?php

/**
 * ==========================================================
 * MODUL       : AutomationEngineSchemaTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi skema AE-1 (F-151/158/159/161) — kolom baru ADA, idempotency
 *               index (F-61) menolak duplikat tapi nullable-safe untuk task manual,
 *               migrasi data template lama BENAR angkanya, kolom recurrence lama (F-121)
 *               tetap utuh, model fillable/cast sinkron skema.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : migration 2026_08_04_100300 (dipanggil langsung untuk uji ulang logic
 *               transformasi data — RefreshDatabase menjalankan migration SEKALI saat
 *               task_templates masih kosong, jadi UPDATE di migration itu 0 baris kena;
 *               di sini logic yang sama diuji ulang terhadap baris legacy buatan test)
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test ini pagar F-121 (kolom lama tak boleh hilang) dan F-61 (idempotency
 *               index harus benar-benar aktif, bukan cuma ada di migration file).
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

function createAutomationSchemaProject(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Automation Schema Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    TaskStatus::seedDefaults($project);

    return $project;
}

test('E1: kolom automation engine ada di task_templates, tasks, automation_run_log', function () {
    foreach ([
        'anchor_strategy',
        'interval_value',
        'interval_unit',
        'anchor_config',
        'date_window_config',
        'max_active_instances',
        'blocked_since',
        'last_block_notified_at',
    ] as $column) {
        expect(Schema::hasColumn('task_templates', $column))->toBeTrue("task_templates.$column hilang");
    }

    expect(Schema::hasColumn('tasks', 'period_key'))->toBeTrue();
    expect(Schema::hasColumn('tasks', 'task_template_id'))->toBeTrue();

    expect(Schema::hasTable('automation_run_log'))->toBeTrue();
    foreach ([
        'id', 'organization_id', 'task_template_id', 'run_at', 'action',
        'reason', 'target_date', 'delta_days', 'meta', 'created_at', 'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('automation_run_log', $column))->toBeTrue("automation_run_log.$column hilang");
    }
});

test('E2: migrasi data menurunkan anchor_strategy/interval benar dari task_type lama (angka terverifikasi)', function () {
    $admin = User::factory()->admin()->create();
    $project = createAutomationSchemaProject($admin);

    // Simulasikan state LEGACY: interval_value/interval_unit masih kosong,
    // persis seperti baris yang sudah ada SEBELUM migration data F-159 poin 4
    // dijalankan (RefreshDatabase menjalankan migration itu saat tabel masih
    // kosong, jadi logic transformasinya perlu diuji ulang terhadap data nyata).
    $daily = TaskTemplate::create([
        'organization_id' => $admin->organization_id, 'project_id' => $project->id,
        'title' => 'Legacy Daily', 'task_type' => 'daily', 'estimated_minutes' => 30,
        'points' => 5, 'priority' => 'normal', 'recurrence_config' => [], 'default_assignees' => [],
    ]);
    $weekly = TaskTemplate::create([
        'organization_id' => $admin->organization_id, 'project_id' => $project->id,
        'title' => 'Legacy Weekly', 'task_type' => 'weekly', 'estimated_minutes' => 60,
        'points' => 10, 'priority' => 'normal', 'recurrence_config' => ['day_of_week' => 1], 'default_assignees' => [],
    ]);
    $monthly = TaskTemplate::create([
        'organization_id' => $admin->organization_id, 'project_id' => $project->id,
        'title' => 'Legacy Monthly', 'task_type' => 'monthly', 'estimated_minutes' => 90,
        'points' => 15, 'priority' => 'normal', 'recurrence_config' => ['day_of_month' => 1], 'default_assignees' => [],
    ]);

    expect($daily->interval_value)->toBeNull();
    expect($daily->interval_unit)->toBeNull();

    $migration = require database_path('migrations/2026_08_04_100300_migrate_legacy_recurrence_to_automation_columns.php');
    $migration->up();

    // Jalankan DUA KALI -- membuktikan idempotent (D2), hasil akhir identik.
    $migration->up();

    expect($daily->refresh()->anchor_strategy)->toBe('time_based');
    expect($daily->interval_value)->toBe(1);
    expect($daily->interval_unit)->toBe('day');

    expect($weekly->refresh()->interval_value)->toBe(1);
    expect($weekly->interval_unit)->toBe('week');

    expect($monthly->refresh()->interval_value)->toBe(1);
    expect($monthly->interval_unit)->toBe('month');

    // Bukti angka: 3 template legacy, 3 baris terkonversi, 0 tersisa NULL.
    $convertedCount = TaskTemplate::whereIn('id', [$daily->id, $weekly->id, $monthly->id])
        ->whereNotNull('interval_value')
        ->count();
    expect($convertedCount)->toBe(3);
});

test('E3: unique (task_template_id, period_key) menolak duplikat generated, nullable-safe untuk task manual', function () {
    $admin = User::factory()->admin()->create();
    $project = createAutomationSchemaProject($admin);
    $statusId = TaskStatus::where('project_id', $project->id)->orderBy('position')->value('id');
    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id, 'project_id' => $project->id,
        'title' => 'Template E3', 'task_type' => 'daily', 'estimated_minutes' => 30,
        'points' => 5, 'priority' => 'normal', 'recurrence_config' => [], 'default_assignees' => [],
    ]);

    $baseTask = [
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $statusId,
        'title' => 'Instance',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'due_date' => now()->addDay(),
        'created_by' => $admin->id,
    ];

    // Dua task MANUAL (task_template_id & period_key null) -- TIDAK boleh tabrakan.
    // NOTE: array_merge (bukan operator `+`) -- `+` mempertahankan value array KIRI
    // saat key bentrok, jadi 'title' override tidak pernah kepakai kalau pakai `+`.
    Task::create(array_merge($baseTask, ['title' => 'Manual 1']));
    Task::create(array_merge($baseTask, ['title' => 'Manual 2']));
    expect(Task::where('title', 'like', 'Manual%')->count())->toBe(2);

    // Generated pertama untuk periode ini -- lolos.
    Task::create(array_merge($baseTask, [
        'title' => 'Generated', 'task_template_id' => $template->id, 'period_key' => '2026-08-04',
    ]));

    // Generated KEDUA, periode SAMA, template SAMA -- WAJIB ditolak (F-61 idempotency).
    expect(fn () => Task::create(array_merge($baseTask, [
        'title' => 'Generated Dupe', 'task_template_id' => $template->id, 'period_key' => '2026-08-04',
    ])))->toThrow(QueryException::class);
});

test('E4: kolom recurrence lama masih ada (F-121) -- engine lama tetap bisa generate', function () {
    expect(Schema::hasColumn('task_templates', 'task_type'))->toBeTrue();
    expect(Schema::hasColumn('task_templates', 'recurrence_config'))->toBeTrue();
    expect(Schema::hasColumn('task_templates', 'default_assignees'))->toBeTrue();
    expect(Schema::hasColumn('task_templates', 'last_generated_date'))->toBeTrue();
    expect(Schema::hasColumn('task_templates', 'is_active'))->toBeTrue();
});

test('E5: TaskTemplate fillable/cast mencakup kolom automation engine', function () {
    $template = new TaskTemplate;

    foreach ([
        'anchor_strategy', 'interval_value', 'interval_unit', 'anchor_config',
        'date_window_config', 'max_active_instances', 'blocked_since', 'last_block_notified_at',
    ] as $field) {
        expect($template->isFillable($field))->toBeTrue("TaskTemplate.$field tidak fillable");
    }

    $casts = $template->getCasts();
    expect($casts['anchor_config'])->toBe('array');
    expect($casts['date_window_config'])->toBe('array');
    expect($casts['blocked_since'])->toBe('date');
    expect($casts['last_block_notified_at'])->toBe('datetime');

    $task = new Task;
    expect($task->isFillable('period_key'))->toBeTrue();
});

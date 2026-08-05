<?php

/**
 * ==========================================================
 * MODUL       : CutoverTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : FASE B (prompt AE-3) — buktikan cutover F-162 TERTUTUP: hanya
 *               `automation:run` yang terjadwal sebagai generator, `tasks:generate-
 *               recurring` (lama) TIDAK lagi di scheduler TAPI code-nya tetap ada
 *               dan bisa dijalankan MANUAL (rollback).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : Illuminate\Console\Scheduling\Schedule (introspeksi event terjadwal),
 *               RunAutomationEngineCommand, GenerateRecurringTasksCommand (manual)
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test ini pagar F-162 (dual-engine) -- kalau suatu saat baris
 *               Schedule::command('tasks:generate-recurring') diam-diam
 *               dikembalikan ke routes/console.php (mis. merge konflik), test
 *               PERTAMA di bawah gagal, mencegah risiko double-generation lolos ke produksi.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;

test('F-162: hanya automation:run yang terjadwal sebagai generator, tasks:generate-recurring TIDAK ada', function () {
    $schedule = app(Schedule::class);

    $commands = collect($schedule->events())
        ->map(fn ($event) => $event->command)
        ->filter(fn (?string $command) => $command !== null);

    $generatorCommands = $commands->filter(
        fn (string $command) => str_contains($command, 'automation:run') || str_contains($command, 'tasks:generate-recurring')
    );

    expect($generatorCommands)->toHaveCount(1);
    expect($generatorCommands->first())->toContain('automation:run');
});

test('rollback: tasks:generate-recurring (lama) TIDAK dihapus, tetap bisa dijalankan MANUAL', function () {
    $admin = User::factory()->admin()->create();
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Cutover Rollback Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);
    TaskStatus::seedDefaults($project);

    WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => Carbon::create(2026, 1, 1)->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Rollback Manual Template',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => [],
        'is_active' => true,
    ]);

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 5, 0)); // Senin

    // Command lama MASIH terdaftar & bisa jalan manual -- CODE tidak dihapus (F-162).
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    expect(Task::where('task_template_id', $template->id)->count())->toBe(1);
});

test('pasca-cutover: template existing tetap tergenerate benar lewat automation:run, nol dobel', function () {
    $admin = User::factory()->admin()->create();
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Cutover Postcheck Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);
    TaskStatus::seedDefaults($project);

    WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => Carbon::create(2026, 1, 1)->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);

    // Template gaya lama, sudah lewat migrasi data F-159 poin 4 (anchor_strategy
    // time_based, interval diturunkan dari task_type='daily').
    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Postcutover Template',
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
    ]);

    $this->travelTo(Carbon::create(2026, 8, 3, 0, 1, 0)); // Senin

    // HANYA automation:run yang dipanggil (simulasi keadaan produksi pasca-cutover) --
    // tasks:generate-recurring TIDAK dipanggil sama sekali di sini.
    $this->artisan('automation:run')->assertSuccessful();
    $this->artisan('automation:run')->assertSuccessful();

    expect(Task::where('task_template_id', $template->id)->count())->toBe(1);
});

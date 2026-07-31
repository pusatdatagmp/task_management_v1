<?php

/**
 * ==========================================================
 * MODUL       : RecurringMonthlyTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi engine recurring untuk task_type=monthly (F-46, v0.8 H4) —
 *               clamp ke akhir bulan (F-101), lalu geser ke hari kerja (F-102),
 *               URUTAN clamp-DULU-baru-shift, termasuk kasus shift melintasi
 *               batas bulan.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : GenerateRecurringTasksCommand
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test "31 di Feb -> clamp 28 -> geser 2 Maret" adalah kasus yang
 *               PERSIS memicu bug tersembunyi kalau algoritma cuma mengecek
 *               periode (bulan) yang mengandung hari ini -- occurrence yang lahir
 *               dari klem Februari tidak akan pernah ketemu begitu tanggal
 *               berjalan sudah masuk Maret (natural date Maret sendiri = 31,
 *               beda sama sekali). Lihat komentar GenerateRecurringTasksCommand.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;

function createMonthlyRecurringProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Monthly Recurring Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function seedMonthlyRecurringSchedule(User $admin, Carbon $effectiveFrom, array $daysOfWeek = [1, 2, 3, 4, 5]): WorkSchedule
{
    return WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => $effectiveFrom->toDateString(),
        'days_of_week' => $daysOfWeek,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);
}

function createMonthlyTemplate(Project $project, int $dayOfMonth, array $assigneeIds = []): TaskTemplate
{
    return TaskTemplate::create([
        'organization_id' => $project->organization_id,
        'project_id' => $project->id,
        'title' => 'Monthly template '.uniqid(),
        'task_type' => 'monthly',
        'estimated_minutes' => 90,
        'points' => 15,
        'priority' => 'normal',
        'recurrence_config' => ['day_of_month' => $dayOfMonth],
        'default_assignees' => $assigneeIds,
        'is_active' => true,
    ]);
}

test('day_of_month=31 di bulan 30 hari -> clamp ke 30, tidak perlu geser (F-101)', function () {
    $admin = User::factory()->admin()->create();
    $project = createMonthlyRecurringProject($admin);
    seedMonthlyRecurringSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createMonthlyTemplate($project, 31);

    // April 2026 cuma 30 hari. 30 April 2026 = Kamis (hari kerja) -- clamp murni,
    // TANPA shift, supaya kasus ini terisolasi dari F-102.
    $this->travelTo(Carbon::create(2026, 4, 29, 0, 0, 0));
    $this->artisan('tasks:generate-recurring')->assertSuccessful();
    expect(Task::where('task_template_id', $template->id)->count())->toBe(0);

    $this->travelTo(Carbon::create(2026, 4, 30, 0, 0, 0));
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $tasks = Task::where('task_template_id', $template->id)->get();
    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->due_date->toDateString())->toBe('2026-04-30');
});

test('day_of_month=31, 28 Feb Sabtu -> clamp DULU ke 28, BARU geser ke Senin 2 Maret (urutan F-101 lalu F-102)', function () {
    $admin = User::factory()->admin()->create();
    $project = createMonthlyRecurringProject($admin);
    seedMonthlyRecurringSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createMonthlyTemplate($project, 31);

    // 2026 bukan tahun kabisat -- Februari 28 hari, dan 28 Feb 2026 = Sabtu.
    $this->travelTo(Carbon::create(2026, 2, 28, 0, 0, 0));
    $this->artisan('tasks:generate-recurring')->assertSuccessful();
    expect(Task::where('task_template_id', $template->id)->count())->toBe(0);

    $this->travelTo(Carbon::create(2026, 3, 1, 0, 0, 0)); // Minggu
    $this->artisan('tasks:generate-recurring')->assertSuccessful();
    expect(Task::where('task_template_id', $template->id)->count())->toBe(0);

    // Senin 2 Maret -- occurrence Februari yang tergeser, MELINTASI batas bulan.
    $this->travelTo(Carbon::create(2026, 3, 2, 0, 0, 0));
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $tasks = Task::where('task_template_id', $template->id)->get();
    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->due_date->toDateString())->toBe('2026-03-02');

    $template->refresh();
    expect($template->last_generated_date->toDateString())->toBe('2026-03-02');

    // Idempotency (F-61): jalan lagi hari yang sama tidak menggandakan.
    $this->artisan('tasks:generate-recurring')->assertSuccessful();
    expect(Task::where('task_template_id', $template->id)->count())->toBe(1);
});

test('day_of_month normal jatuh hari kerja -> generate tepat di tanggal itu', function () {
    $admin = User::factory()->admin()->create();
    $project = createMonthlyRecurringProject($admin);
    seedMonthlyRecurringSchedule($admin, Carbon::create(2026, 1, 1));
    $template = createMonthlyTemplate($project, 10);

    $this->travelTo(Carbon::create(2026, 8, 9, 0, 0, 0)); // sehari sebelumnya
    $this->artisan('tasks:generate-recurring')->assertSuccessful();
    expect(Task::where('task_template_id', $template->id)->count())->toBe(0);

    $this->travelTo(Carbon::create(2026, 8, 10, 0, 0, 0)); // Senin, tanggal 10
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $tasks = Task::where('task_template_id', $template->id)->get();
    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->due_date->toDateString())->toBe('2026-08-10');
});

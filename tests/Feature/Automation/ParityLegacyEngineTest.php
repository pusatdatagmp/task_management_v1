<?php

/**
 * ==========================================================
 * MODUL       : ParityLegacyEngineTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : FASE A (prompt AE-3) — buktikan engine baru (`automation:run`)
 *               menghasilkan tanggal task YANG SAMA dengan semantik engine lama
 *               untuk template hasil migrasi (daily/weekly/monthly -> time_based),
 *               KASUS NORMAL (cron jalan tiap hari, TANPA miss-run). Perbedaan
 *               yang SENGAJA (F-152 catch-up-satu, F-153 daily ikut shift) diuji
 *               terpisah di RunAutomationEngineCommandTest/HolidayShiftResolverTest
 *               -- di sini murni "apakah himpunan tanggal due_date yang lahir
 *               sama dengan yang akan dihasilkan engine lama".
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : RunAutomationEngineCommand (via $this->artisan(), disimulasikan
 *               berjalan HARIAN lewat loop travelTo())
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : SUMBER temuan LANGKAH 0 -- weekly/monthly time_based TIDAK
 *               membaca recurrence_config.day_of_week/day_of_month sama sekali;
 *               anchor hari-tetap dipertahankan HANYA karena dueline (last_generated_date
 *               + interval) secara aritmatik kembali ke hari yang sama SELAMA cron
 *               tidak pernah bolong (kasus di sini). Test ini TIDAK menguji skenario
 *               miss-run (itu tetap DRIFT permanen, lihat laporan LANGKAH 0) --
 *               kalau loop di bawah diubah untuk "melewati" beberapa hari (skip
 *               travelTo), parity ini TIDAK lagi berlaku, itu ekspektasi yang benar.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;

function createParityProject(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Parity Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);
    TaskStatus::seedDefaults($project);

    return $project;
}

function seedParitySchedule(User $admin): WorkSchedule
{
    return WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => Carbon::create(2026, 1, 1)->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);
}

/**
 * KONTRAK: jalankan `automation:run` SEKALI per hari kalender dari $from s/d $to
 * INKLUSIF -- simulasi cron 00:01 WIB harian TANPA satu hari pun bolong (kasus
 * normal FASE A, BUKAN miss-run).
 */
function runDailyCronBetween($test, Carbon $from, Carbon $to): void
{
    $cursor = $from->copy();

    while ($cursor->lessThanOrEqualTo($to)) {
        $test->travelTo($cursor->copy()->setTime(0, 1));
        $test->artisan('automation:run')->assertSuccessful();
        $cursor->addDay();
    }
}

test('parity DAILY: himpunan due_date = seluruh hari kerja dalam rentang (setara engine lama)', function () {
    $admin = User::factory()->admin()->create();
    $project = createParityProject($admin);
    seedParitySchedule($admin);

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Parity Daily',
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

    // Senin 3 Agustus s/d Rabu 12 Agustus 2026 -- 10 hari kalender, melintasi
    // SATU weekend (8-9 Agustus), 8 di antaranya hari kerja.
    runDailyCronBetween($this, Carbon::create(2026, 8, 3), Carbon::create(2026, 8, 12));

    $dueDates = Task::where('task_template_id', $template->id)
        ->get()
        ->map(fn (Task $t) => $t->due_date->toDateString())
        ->sort()
        ->values()
        ->all();

    $expectedBusinessDays = ['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07', '2026-08-10', '2026-08-11', '2026-08-12'];

    expect($dueDates)->toBe($expectedBusinessDays);
});

test('parity WEEKLY (day_of_week=Senin): himpunan due_date = tiap Senin, setara engine lama', function () {
    $admin = User::factory()->admin()->create();
    $project = createParityProject($admin);
    seedParitySchedule($admin);

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Parity Weekly',
        'task_type' => 'weekly',
        'estimated_minutes' => 60,
        'points' => 10,
        'priority' => 'normal',
        'recurrence_config' => ['day_of_week' => 1],
        'default_assignees' => [],
        'is_active' => true,
        'anchor_strategy' => 'time_based',
        'interval_value' => 1,
        'interval_unit' => 'week',
        // SUMBER: representasi "kapan terakhir digenerate engine lama" -- Senin
        // 27 Juli 2026 (siklus SEBELUM window pengamatan test ini), pola sama
        // migrasi data F-159 poin 4 (kolom ini TIDAK direkonstruksi ulang).
        'last_generated_date' => '2026-07-27',
    ]);

    // Selasa 28 Juli s/d Selasa 11 Agustus -- melintasi TEPAT 2 hari Senin (3 & 10 Agustus).
    runDailyCronBetween($this, Carbon::create(2026, 7, 28), Carbon::create(2026, 8, 11));

    $dueDates = Task::where('task_template_id', $template->id)
        ->get()
        ->map(fn (Task $t) => $t->due_date->toDateString())
        ->sort()
        ->values()
        ->all();

    expect($dueDates)->toBe(['2026-08-03', '2026-08-10']);
});

test('parity MONTHLY (day_of_month=1): jatuh di Sabtu -> digeser Senin, setara engine lama', function () {
    $admin = User::factory()->admin()->create();
    $project = createParityProject($admin);
    seedParitySchedule($admin);

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Parity Monthly',
        'task_type' => 'monthly',
        'estimated_minutes' => 90,
        'points' => 15,
        'priority' => 'normal',
        'recurrence_config' => ['day_of_month' => 1],
        'default_assignees' => [],
        'is_active' => true,
        'anchor_strategy' => 'time_based',
        'interval_value' => 1,
        'interval_unit' => 'month',
        // 1 Juli 2026 = Rabu (hari kerja, tidak pernah butuh shift saat itu).
        'last_generated_date' => '2026-07-01',
    ]);

    // 1 Agustus 2026 = Sabtu -- engine lama akan menggeser ke Senin 3 Agustus
    // (F-101 clamp tak relevan di sini, tanggal 1 selalu ada tiap bulan).
    runDailyCronBetween($this, Carbon::create(2026, 7, 2), Carbon::create(2026, 8, 5));

    $tasks = Task::where('task_template_id', $template->id)->get();

    expect($tasks)->toHaveCount(1);
    expect($tasks->first()->due_date->toDateString())->toBe('2026-08-03');
});

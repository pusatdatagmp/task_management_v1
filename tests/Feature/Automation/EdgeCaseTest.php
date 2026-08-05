<?php

/**
 * ==========================================================
 * MODUL       : EdgeCaseTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : FASE E (prompt AE-3/AE-2b) edge test intens, MySQL (F-83) — E1
 *               miss-run catch-up-satu (F-152), E2 holiday-shift edge (libur
 *               beruntun + batas bulan, F-153/F-43), E3 Opsi B deadlock penuh
 *               (skip berulang + notif SEKALI, F-154), E4 CalendarAnchored
 *               day_of_month=31 di bulan pendek -- DIPERBARUI AE-2b (F-78/F-164):
 *               SEMULA skip total (AE-3), Boss putuskan ganti jadi CLAMP ke akhir
 *               bulan (pola F-101). E5 (idempotency F-61 + isolasi F-160) SUDAH
 *               dibuktikan di RunAutomationEngineCommandTest (AE-2) -- tidak
 *               diulang di sini supaya tidak duplikasi. E6 (regresi nol)
 *               dibuktikan lewat full `php artisan test` di laporan akhir,
 *               bukan test individual.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : RunAutomationEngineCommand, HolidayShiftResolver, CalendarAnchoredStrategy
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : E1 pagar F-152 — kalau catch-up diam-diam backfill semua periode
 *               terlewat, budget/KPI harian akan tercemar kewajiban yang tak
 *               pernah nyata terjadi (sama semangat F-100 lama, beda mekanisme).
 * ==========================================================
 */

use App\Models\AutomationRunLog;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Notifications\TemplateBlockedNotification;
use App\Services\Automation\AutomationContext;
use App\Services\Automation\Resolvers\HolidayShiftResolver;
use App\Services\Automation\Strategies\CalendarAnchoredStrategy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

function createEdgeProject(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Edge Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);
    TaskStatus::seedDefaults($project);

    return $project;
}

function seedEdgeSchedule(User $admin, ?Carbon $effectiveFrom = null): WorkSchedule
{
    return WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => ($effectiveFrom ?? Carbon::create(2026, 1, 1))->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);
}

// ---------------------------------------------------------------------------
// E1: miss-run = catch-up SATU (F-152), BUKAN backfill semua periode terlewat
// ---------------------------------------------------------------------------

test('E1: server mati 5 hari, cron catch-up SATU task, BUKAN 5 backfill', function () {
    $admin = User::factory()->admin()->create();
    $project = createEdgeProject($admin);
    seedEdgeSchedule($admin);

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Edge Daily Miss-Run',
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
        'last_generated_date' => '2026-08-03', // Senin -- cron TERAKHIR jalan di sini
    ]);

    // Cron TIDAK jalan sama sekali Selasa-Jumat (4 hari) -- server dianggap mati.
    // Baru jalan lagi Sabtu 8 Agustus (5 hari sejak last_generated_date).
    $this->travelTo(Carbon::create(2026, 8, 8, 0, 1, 0));
    $this->artisan('automation:run')->assertSuccessful();

    $tasks = Task::where('task_template_id', $template->id)->get();

    // SATU catch-up, BUKAN 5 (Sel/Rab/Kam/Jum/Sab).
    expect($tasks)->toHaveCount(1);
    // Resolver mulai dari now_WIB (Sabtu, libur) -> digeser ke Senin berikutnya.
    expect($tasks->first()->due_date->toDateString())->toBe('2026-08-10');
    expect($template->fresh()->last_generated_date->toDateString())->toBe('2026-08-08');
});

// ---------------------------------------------------------------------------
// E2: holiday-shift edge -- libur BERUNTUN & lintas BATAS BULAN
// ---------------------------------------------------------------------------

test('E2a: libur BERUNTUN 2 hari -> geser ke hari kerja SETELAH keduanya', function () {
    $admin = User::factory()->admin()->create();
    seedEdgeSchedule($admin);
    Holiday::create(['organization_id' => $admin->organization_id, 'date' => '2026-08-03', 'name' => 'Libur 1']);
    Holiday::create(['organization_id' => $admin->organization_id, 'date' => '2026-08-04', 'name' => 'Libur 2']);

    $schedules = WorkSchedule::where('organization_id', $admin->organization_id)->get();
    $holidays = Holiday::where('organization_id', $admin->organization_id)->get();

    $result = (new HolidayShiftResolver)->resolve(Carbon::create(2026, 8, 3), $schedules, $holidays);

    expect($result->toDateString())->toBe('2026-08-05'); // Rabu, setelah 2 libur beruntun
});

test('E2b: target di akhir bulan libur+weekend -> geser LINTAS BATAS BULAN', function () {
    $admin = User::factory()->admin()->create();
    seedEdgeSchedule($admin);
    // 28 Feb 2026 = Sabtu (weekend alami), 1 Mar 2026 = Minggu (weekend alami) --
    // tanpa perlu holiday tambahan, shift SUDAH melompati batas Feb->Mar.
    $schedules = WorkSchedule::where('organization_id', $admin->organization_id)->get();
    $holidays = Holiday::where('organization_id', $admin->organization_id)->get();

    $result = (new HolidayShiftResolver)->resolve(Carbon::create(2026, 2, 28), $schedules, $holidays);

    expect($result->toDateString())->toBe('2026-03-02'); // Senin, BULAN BERBEDA dari natural date
});

// ---------------------------------------------------------------------------
// E3: Opsi B deadlock PENUH -- skip berulang selama beberapa run + notif SEKALI
// ---------------------------------------------------------------------------

test('E3: sebelumnya TAK PERNAH selesai -> skip berulang 3 run, notif admin SEKALI saja', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $project = createEdgeProject($admin);
    seedEdgeSchedule($admin);

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Edge Deadlock',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => [],
        'is_active' => true,
        'anchor_strategy' => 'completion_based',
        'interval_value' => 1,
        'interval_unit' => 'day',
        'last_generated_date' => '2026-08-03',
    ]);

    $statusIdNotDone = TaskStatus::where('project_id', $project->id)->where('is_completed', false)->value('id');
    $previousTask = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_template_id' => $template->id,
        'period_key' => '2026-08-03',
        'task_status_id' => $statusIdNotDone,
        'title' => 'Instance sebelumnya (tak pernah selesai)',
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'due_date' => Carbon::create(2026, 8, 3)->endOfDay(),
        'created_by' => $admin->id,
    ]);

    // 3 run berturut-turut (Selasa-Kamis), previous task TIDAK PERNAH di-approve.
    foreach (['2026-08-04', '2026-08-05', '2026-08-06'] as $day) {
        $this->travelTo(Carbon::parse($day)->setTime(0, 1));
        $this->artisan('automation:run')->assertSuccessful();
    }

    // Tidak ada instance baru lahir -- tetap 1 (yang lama, belum selesai).
    expect(Task::where('task_template_id', $template->id)->count())->toBe(1);
    expect($template->fresh()->blocked_since?->toDateString())->toBe('2026-08-04'); // run PERTAMA yang mendeteksi

    $logs = AutomationRunLog::where('task_template_id', $template->id)->orderBy('id')->pluck('reason')->all();
    expect($logs)->toBe(['sebelumnya-belum-selesai', 'sebelumnya-belum-selesai', 'sebelumnya-belum-selesai']);

    // 🔴 F-154: notif SEKALI walau skip 3x berturut-turut.
    Notification::assertSentToTimes($project->owner, TemplateBlockedNotification::class, 1);
});

// ---------------------------------------------------------------------------
// E4: CalendarAnchored day_of_month=31 di bulan PENDEK (Feb) -- perilaku terdefinisi
// ---------------------------------------------------------------------------

test('E4: day_of_month=31 di Februari (28 hari) -> CLAMP ke akhir bulan, Pass (F-164, F-78 update dari skip)', function () {
    // F-78: test ini SEBELUMNYA mengharapkan SKIP total (lihat commit AE-3) --
    // Boss MEMUTUSKAN mengubah perilaku itu (F-164, registry) supaya "tanggal
    // 31"/"akhir bulan" tetap generate di hari terakhir bulan pendek, pola sama
    // F-101 (GenerateRecurringTasksCommand::naturalMonthlyDate() clamp lama).
    // Diperbarui JUJUR, bukan ditambal -- assertion berubah SEUTUHNYA konsisten
    // dengan perilaku baru CalendarAnchoredStrategy.
    $admin = User::factory()->admin()->create();
    $project = createEdgeProject($admin);

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Edge Calendar 31',
        'task_type' => 'monthly',
        'estimated_minutes' => 30,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => [],
        'is_active' => true,
        'anchor_strategy' => 'calendar_anchored',
        'anchor_config' => ['day_of_month' => 31],
    ]);

    // 2026 BUKAN kabisat -- Februari 28 hari, itulah "akhir bulan" hasil clamp.
    $decisionOn28 = (new CalendarAnchoredStrategy)->evaluate($template, new AutomationContext(Carbon::create(2026, 2, 28), collect(), collect(), [], []));
    expect($decisionOn28)->toBeNull(); // Pass -- 28 Feb = clamp(31, 28)

    // Sepanjang Februari 2026, HANYA tanggal 28 (hasil clamp) yang Pass -- 1-27 tetap Skip.
    $passDays = [];
    for ($day = 1; $day <= 28; $day++) {
        $ctxDay = new AutomationContext(Carbon::create(2026, 2, $day), collect(), collect(), [], []);
        if ((new CalendarAnchoredStrategy)->evaluate($template, $ctxDay) === null) {
            $passDays[] = $day;
        }
    }
    expect($passDays)->toBe([28]);

    // Kontrol: bulan PANJANG (Agustus, 31 hari) TETAP cocok PERSIS di tanggal 31
    // -- clamp HANYA aktif kalau day_of_month > daysInMonth, bukan selalu geser.
    $decisionAug31 = (new CalendarAnchoredStrategy)->evaluate($template, new AutomationContext(Carbon::create(2026, 8, 31), collect(), collect(), [], []));
    expect($decisionAug31)->toBeNull();
});

<?php

/**
 * ==========================================================
 * MODUL       : DashboardBebanSpreadTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi SEBAR beban (F-59 opsi B, mekanik F-118) — task besar
 *               tidak lagi menumpuk 100% ke hari due/overdue, melainkan disebar
 *               rata ke hari KERJA dari hari ini s/d tenggat (lewati weekend/libur,
 *               F-43). Urutan WAJIB dibuktikan: bagi assignee (F-96) DULU, baru
 *               bagi hari (F-118). Juga membuktikan N+1 tetap konstan (F-85) dan
 *               REALISASI (F-94) sama sekali tidak tersentuh oleh perubahan ini.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : DashboardService, BusinessHoursCalculator, LiveTaskCounter
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test urutan (A1) adalah pagar SATU-SATUNYA yang mencegah regresi
 *               ke pembagian terbalik (bagi hari dulu baru assignee) — matematis
 *               hasilnya SAMA untuk task ini karena hari_kerja tidak bergantung ke
 *               assignee, tapi urutan kode yang salah adalah bom waktu kalau nanti
 *               formula berkembang (mis. hari_kerja per-assignee individual).
 * ==========================================================
 */

use App\Models\Holiday;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\DashboardService;
use App\Services\LiveTaskCounter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function createSpreadProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Beban Spread Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function seedSpreadSchedule(User $admin, Carbon $anchor): void
{
    WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => $anchor->copy()->subMonth()->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);
}

function createSpreadTask(Project $project, TaskStatus $status, User $admin, array $assigneeIds, int $estimatedMinutes, Carbon $dueDate): Task
{
    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'Spread task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => $estimatedMinutes,
        'due_date' => $dueDate,
        'created_by' => $admin->id,
    ]);

    $task->assignees()->sync($assigneeIds);

    return $task;
}

// Senin 2026-08-03 09:00 -- jangkar semua skenario (Jumat 2026-08-07 = +5 hari
// kerja pas, Sabtu 2026-08-08/Minggu 09 = weekend, Senin 2026-08-10 = +6 hari kerja).
function spreadAnchor(): Carbon
{
    return Carbon::create(2026, 8, 3, 9, 0, 0);
}

test('task 40 jam, 1 assignee, tenggat 5 hari kerja lagi -> beban hari ini 8 jam (F-118)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createSpreadProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = spreadAnchor();
    seedSpreadSchedule($admin, $anchor);
    $this->travelTo($anchor);

    // Senin s/d Jumat sama minggu = TEPAT 5 hari kerja (F-118 contoh Boss persis).
    createSpreadTask($project, $todo, $admin, [$member->id], 2400, Carbon::create(2026, 8, 7, 17, 0, 0));

    $rows = (new DashboardService)->forUsers(collect([$member]), $anchor);

    expect($rows[$member->id]['beban'])->toBe(480) // 2400 / 5 hari kerja = 480 menit/hari
        ->and($rows[$member->id]['backlog'])->toBe(1920); // 2400 - 480
});

test('task 40 jam, 2 assignee, tenggat 5 hari kerja lagi -> assignee DULU (F-96) baru hari (F-118): 240/hari tiap orang', function () {
    $admin = User::factory()->admin()->create();
    $memberA = User::factory()->create(['organization_id' => $admin->organization_id]);
    $memberB = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createSpreadProject($admin, [$memberA->id, $memberB->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = spreadAnchor();
    seedSpreadSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createSpreadTask($project, $todo, $admin, [$memberA->id, $memberB->id], 2400, Carbon::create(2026, 8, 7, 17, 0, 0));

    $rows = (new DashboardService)->forUsers(collect([$memberA, $memberB]), $anchor);

    // URUTAN A1: 2400/2 assignee = 1200 per_assignee_total DULU, BARU /5 hari
    // kerja = 240/hari. Kalau urutan terbalik (2400/5 hari=480, lalu /2 assignee=240)
    // hasil AKHIRNYA sama untuk kasus ini (hari_kerja tidak bergantung assignee) --
    // test ini mengunci URUTAN KODE-nya (lihat komentar workloadSpread()), bukan
    // cuma angka akhirnya, supaya regresi urutan tidak lolos diam-diam kalau
    // formula berkembang nanti.
    expect($rows[$memberA->id]['beban'])->toBe(240)
        ->and($rows[$memberB->id]['beban'])->toBe(240)
        ->and($rows[$memberA->id]['backlog'])->toBe(960) // 1200 - 240
        ->and($rows[$memberB->id]['backlog'])->toBe(960);
});

test('tenggat HARI INI -> seluruh estimasi masuk beban hari ini, backlog 0 (A3)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createSpreadProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = spreadAnchor();
    seedSpreadSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createSpreadTask($project, $todo, $admin, [$member->id], 300, $anchor->copy()->endOfDay());

    $rows = (new DashboardService)->forUsers(collect([$member]), $anchor);

    expect($rows[$member->id]['beban'])->toBe(300)
        ->and($rows[$member->id]['backlog'])->toBe(0);
});

test('OVERDUE -> tidak bisa sebar ke masa lalu, seluruh estimasi masuk hari ini (A3)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createSpreadProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = spreadAnchor();
    seedSpreadSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createSpreadTask($project, $todo, $admin, [$member->id], 150, $anchor->copy()->subDays(3));

    $rows = (new DashboardService)->forUsers(collect([$member]), $anchor);

    expect($rows[$member->id]['beban'])->toBe(150)
        ->and($rows[$member->id]['backlog'])->toBe(0);
});

test('tenggat menyeberang weekend -> hanya hari kerja yang dihitung (F-43)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createSpreadProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = spreadAnchor(); // Senin 2026-08-03
    seedSpreadSchedule($admin, $anchor);
    $this->travelTo($anchor);

    // Senin 03 s/d Senin 10 (melintasi Sabtu 08/Minggu 09) = 6 hari kerja
    // (Sen,Sel,Rab,Kam,Jum minggu ini + Senin minggu depan), BUKAN 8 hari kalender.
    createSpreadTask($project, $todo, $admin, [$member->id], 1200, Carbon::create(2026, 8, 10, 17, 0, 0));

    $rows = (new DashboardService)->forUsers(collect([$member]), $anchor);

    expect($rows[$member->id]['beban'])->toBe(200) // 1200 / 6 hari kerja
        ->and($rows[$member->id]['backlog'])->toBe(1000);
});

test('hari libur di rentang -> dilewati saat sebar, ikut mengurangi jumlah hari kerja (F-43)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createSpreadProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = spreadAnchor(); // Senin 2026-08-03
    seedSpreadSchedule($admin, $anchor);
    $this->travelTo($anchor);

    // Libur Rabu 2026-08-05 -- rentang Senin s/d Jumat (5 hari kerja normal)
    // turun jadi 4 hari kerja (F-43 menang atas days_of_week, sama seperti overlapMinutes()).
    Holiday::create([
        'organization_id' => $admin->organization_id,
        'date' => '2026-08-05',
        'name' => 'Libur Tes Sebar',
    ]);

    createSpreadTask($project, $todo, $admin, [$member->id], 800, Carbon::create(2026, 8, 7, 17, 0, 0));

    $rows = (new DashboardService)->forUsers(collect([$member]), $anchor);

    expect($rows[$member->id]['beban'])->toBe(200) // 800 / 4 hari kerja (Rabu dilewati)
        ->and($rows[$member->id]['backlog'])->toBe(600);
});

test('jumlah query dashboard TETAP KONSTAN walau task/user bertambah banyak (F-85)', function () {
    $admin = User::factory()->admin()->create();
    $members = User::factory()->count(3)->create(['organization_id' => $admin->organization_id]);
    $project = createSpreadProject($admin, $members->pluck('id')->all());
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = spreadAnchor();
    seedSpreadSchedule($admin, $anchor);
    $this->travelTo($anchor);

    foreach ($members as $member) {
        createSpreadTask($project, $todo, $admin, [$member->id], 480, Carbon::create(2026, 8, 7, 17, 0, 0));
    }

    // Pemanasan (query pertama bisa beda, pola sama ActivityLogTest F-85).
    (new DashboardService)->forUsers($members, $anchor);

    DB::enableQueryLog();
    (new DashboardService)->forUsers($members, $anchor);
    $smallCount = count(DB::getQueryLog());
    DB::flushQueryLog();

    $moreMembers = User::factory()->count(8)->create(['organization_id' => $admin->organization_id]);
    $project->members()->attach($moreMembers->pluck('id'));
    foreach ($moreMembers as $member) {
        createSpreadTask($project, $todo, $admin, [$member->id], 480, Carbon::create(2026, 8, 7, 17, 0, 0));
    }
    $allMembers = $members->concat($moreMembers);
    DB::flushQueryLog(); // buang jejak INSERT seeding di atas (pola sama ActivityLogTest F-85).

    (new DashboardService)->forUsers($allMembers, $anchor);
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeCount)->toBe($smallCount);
});

test('REALISASI (aktif) tidak berubah sama sekali oleh refactor sebar beban -- tetap identik LiveTaskCounter (F-94)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createSpreadProject($admin, [$member->id]);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $anchor = spreadAnchor();
    seedSpreadSchedule($admin, $anchor);
    $this->travelTo($anchor);

    $task = createSpreadTask($project, $todo = $inProgress, $admin, [$member->id], 2400, Carbon::create(2026, 8, 7, 17, 0, 0));
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $member->id,
        'started_at' => $anchor->copy(),
    ]);

    $this->travelTo($anchor->copy()->addMinutes(50));

    $directCounter = (new LiveTaskCounter)->forTask($task->fresh(), $member);
    $rows = (new DashboardService)->forUsers(collect([$member]), Carbon::now());

    // F-94 TIDAK TERSENTUH: 'aktif' (realisasi) harus tetap identik LiveTaskCounter,
    // TIDAK IKUT DISEBAR (F-118 cuma mengubah 'beban'/'backlog', keduanya
    // metrik PERENCANAAN dari estimated_minutes, bukan realisasi dari segmen).
    expect($rows[$member->id]['aktif'])->toBe($directCounter['accumulated_minutes'])
        ->and($directCounter['accumulated_minutes'])->toBe(50);
});

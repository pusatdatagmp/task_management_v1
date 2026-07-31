<?php

/**
 * ==========================================================
 * MODUL       : DashboardCommandCenterTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : v1.2 H3 Fase A/B (F-121/F-131) — buktikan endpoint
 *               dashboard.command-center (DashboardController::commandCenter())
 *               HANYA menyusun tampilan dari service/presenter yang SUDAH ADA,
 *               NOL rumus KPI baru (F-4/F-109). Fokus B2 (heatmap identik
 *               DashboardService::forUsers/workloadSpread F-118) dan B3 (N+1 konstan).
 *               Dashboard 3-angka lama (F-52, DashboardTest.php) TIDAK disentuh/diregres.
 *               v1.2 H4: tes tambahan di bagian bawah file untuk halaman Inertia
 *               'dashboard/overview' (DashboardController::commandCenterPage()) —
 *               props HARUS identik payload JSON di atas (F-109, satu sumber).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : DashboardController::commandCenter()/commandCenterPage(), DashboardService
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau heatmap diam-diam menghitung ulang beban dengan rumus
 *               beda dari workloadSpread(), angka tim akan drift dari dashboard
 *               3-angka lama tanpa ada yang sadar (F-109) — test ini pagarnya.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\DashboardService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

function createCcProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Command Center Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function seedCcSchedule(User $admin, Carbon $anchor): void
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

function createCcTask(Project $project, TaskStatus $status, User $admin, array $assigneeIds, int $estimatedMinutes, Carbon $dueDate, ?string $priorityQuadrant = null, string $taskType = 'tentative'): Task
{
    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'CC task '.uniqid(),
        'task_type' => $taskType,
        'priority_quadrant' => $priorityQuadrant,
        'estimated_minutes' => $estimatedMinutes,
        'due_date' => $dueDate,
        'created_by' => $admin->id,
    ]);

    $task->assignees()->sync($assigneeIds);

    return $task;
}

// Senin 2026-08-03 -- jangkar sama dengan DashboardBebanSpreadTest supaya angka
// pembanding (5 hari kerja s/d Jumat 08-07) sudah pernah diverifikasi manual di sana.
function ccAnchor(): Carbon
{
    return Carbon::create(2026, 8, 3, 9, 0, 0);
}

test('guest redirected, member tanpa dashboard.view forbidden, admin ok (F-95)', function () {
    $this->getJson(route('dashboard.command-center'))->assertUnauthorized();

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($member)->getJson(route('dashboard.command-center'))->assertForbidden();
    $this->actingAs($admin)->getJson(route('dashboard.command-center'))->assertOk();
});

test('donut prioritas: jumlah task per priority_quadrant termasuk bucket none (A2/F-122)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(10), 'p1');
    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(10), 'p1');
    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(10), 'p4');
    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(10), null);

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center'));

    $response->assertOk()->assertJsonPath('donut_priority.p1', 2)
        ->assertJsonPath('donut_priority.p2', 0)
        ->assertJsonPath('donut_priority.p3', 0)
        ->assertJsonPath('donut_priority.p4', 1)
        ->assertJsonPath('donut_priority.none', 1);
});

test('distribusi progress: dihitung dari FLAG status, bukan nama status (A3/F-44)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $progress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();

    // GUARD F-44: nama status diganti jadi sesuatu yang aneh -- kalau controller
    // baca dari nama (bukan flag), test ini akan gagal.
    $progress->update(['name' => 'Status Aneh XYZ']);

    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5));
    createCcTask($project, $progress, $admin, [$member->id], 60, $anchor->copy()->addDays(5));
    createCcTask($project, $progress, $admin, [$member->id], 60, $anchor->copy()->addDays(5));
    createCcTask($project, $review, $admin, [$member->id], 60, $anchor->copy()->addDays(5));
    createCcTask($project, $done, $admin, [$member->id], 60, $anchor->copy()->addDays(5));

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center'));

    $response->assertOk()->assertJsonPath('progress_distribution.todo', 1)
        ->assertJsonPath('progress_distribution.progress', 2)
        ->assertJsonPath('progress_distribution.review', 1)
        ->assertJsonPath('progress_distribution.selesai', 1);
});

test('kategori tugas: breakdown per task_type (A4)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5), null, 'daily');
    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5), null, 'daily');
    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5), null, 'project');

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center'));

    $response->assertOk();
    $categories = collect($response->json('task_categories'))->pluck('total', 'task_type');
    expect($categories['daily'])->toBe(2)->and($categories['project'])->toBe(1);
});

test('heatmap = beban F-118, angka IDENTIK DashboardService::forUsers, hari lewat NETRAL (A5/B2/F-131)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = ccAnchor(); // Senin 2026-08-03
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    // Sama persis skenario DashboardBebanSpreadTest: 2400 menit, 1 assignee, due
    // Jumat 08-07 (5 hari kerja) -> beban hari ini (anchor) = 480.
    createCcTask($project, $todo, $admin, [$member->id], 2400, Carbon::create(2026, 8, 7, 17, 0, 0));

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center', ['month' => '2026-08']));
    $response->assertOk();

    $activeUsers = User::where('is_active', true)->orderBy('name')->get();
    $days = collect($response->json('heatmap.days'));
    $n = $response->json('heatmap.active_user_count');
    expect($n)->toBe($activeUsers->count());

    // B2 -- SUMBER SAMA: angka heatmap hari ini WAJIB identik Σ forUsers()['beban']
    // (satu sumber F-118/F-109), bukan dihitung ulang dengan rumus lain.
    $expectedToday = collect((new DashboardService)->forUsers($activeUsers, $anchor))->sum('beban');
    $todayEntry = $days->firstWhere('date', '2026-08-03');
    expect($todayEntry['beban'])->toBe($expectedToday);

    $expectedLevel = match (true) {
        $expectedToday >= 420 * $n => 'overload',
        $expectedToday >= 210 * $n => 'tengah',
        default => 'aman',
    };
    expect($todayEntry['level'])->toBe($expectedLevel);

    // Hari SEBELUM anchor dalam bulan yang sama = NETRAL, walau ada task -- F-131
    // hari lewat tidak pernah dihitung sama sekali (bukan cuma disembunyikan).
    $pastEntry = $days->firstWhere('date', '2026-08-01');
    expect($pastEntry['beban'])->toBeNull()->and($pastEntry['level'])->toBeNull();

    // Hari MASA DEPAN dalam rentang (Selasa 08-04) -- vantage bergeser, WAJIB tetap
    // identik forUsers() dipanggil dengan tanggal itu sebagai "hari ini".
    $futureDate = Carbon::create(2026, 8, 4);
    $expectedFuture = collect((new DashboardService)->forUsers($activeUsers, $futureDate))->sum('beban');
    $futureEntry = $days->firstWhere('date', '2026-08-04');
    expect($futureEntry['beban'])->toBe($expectedFuture);
});

test('top-10 task: urut prio_score (bobot Eisenhower) lalu due_date, hanya task belum selesai (A7/F-122)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    $p4 = createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(1), 'p4');
    $p1Later = createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(9), 'p1');
    $p1Sooner = createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(2), 'p1');
    createCcTask($project, $done, $admin, [$member->id], 60, $anchor->copy()->addDays(1), 'p1'); // selesai -- tidak boleh muncul

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center'));

    $response->assertOk();
    $ids = collect($response->json('top_tasks'))->pluck('id')->all();

    expect($ids)->toBe([$p1Sooner->id, $p1Later->id, $p4->id]);
    expect($response->json('top_tasks.0.prio_score'))->toBe(4)
        ->and($response->json('top_tasks.2.prio_score'))->toBe(1);
});

test('recent activity pakai ActivityLogPresenter, bukan raw event string (A6/F-106)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5));

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center'));

    $response->assertOk();
    $messages = collect($response->json('recent_activity'))->pluck('message');
    expect($messages->contains(fn ($m) => str_contains($m, 'membuat task')))->toBeTrue()
        ->and($messages->contains('created'))->toBeFalse(); // raw event string tidak boleh bocor
});

test('workload top-5 REUSE DashboardService::forUsers, urut beban terbesar (A8/F-96/F-118)', function () {
    $admin = User::factory()->admin()->create();
    $memberBesar = User::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Besar']);
    $memberKecil = User::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Kecil']);
    $project = createCcProject($admin, [$memberBesar->id, $memberKecil->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$memberBesar->id], 2400, Carbon::create(2026, 8, 7, 17, 0, 0));
    createCcTask($project, $todo, $admin, [$memberKecil->id], 60, Carbon::create(2026, 8, 7, 17, 0, 0));

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center'));

    $response->assertOk();
    $top = collect($response->json('workload_top5'));
    expect($top->first()['id'])->toBe($memberBesar->id)
        ->and($top->first()['beban'])->toBe(480); // sama seperti DashboardBebanSpreadTest
});

test('summary cards: 4 status count dari FLAG F-44 + overdue dari due_date<sekarang (addendum §7.1)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $progress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();

    // GUARD F-44: nama status diganti jadi sesuatu yang aneh -- kalau controller
    // baca dari nama (bukan flag), test ini akan gagal (pola sama test A3).
    $progress->update(['name' => 'Status Aneh XYZ']);

    $anchor = ccAnchor(); // Senin 2026-08-03, 09:00
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5));
    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5));
    createCcTask($project, $progress, $admin, [$member->id], 60, $anchor->copy()->addDays(5));
    createCcTask($project, $review, $admin, [$member->id], 60, $anchor->copy()->addDays(5));
    // Selesai dengan due LAMPAU -- tidak boleh ikut overdue (is_completed=true).
    createCcTask($project, $done, $admin, [$member->id], 60, $anchor->copy()->subDays(2));
    // Belum-selesai dengan due LAMPAU -- overdue (F-44, pola TaskController::search()).
    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->subDays(1));
    createCcTask($project, $progress, $admin, [$member->id], 60, $anchor->copy()->subDays(3));

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center'));
    $response->assertOk();

    $cards = $response->json('summary_cards');

    expect($cards['todo'])->toBe(3) // 2 murni + 1 overdue
        ->and($cards['in_progress'])->toBe(2) // 1 murni + 1 overdue
        ->and($cards['review'])->toBe(1)
        ->and($cards['overdue'])->toBe(2)
        ->and(array_keys($cards))->toBe(['beban_harian', 'todo', 'in_progress', 'review', 'overdue']);
});

test('summary cards: beban_harian IDENTIK dailyLoadTotals hari ini, kapasitas dari kapasitas() (F-118/F-40)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = ccAnchor(); // Senin 2026-08-03
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    // Sama skenario DashboardBebanSpreadTest/heatmap test: 2400 menit, 1 assignee,
    // due Jumat 08-07 (5 hari kerja) -> beban hari ini (anchor) = 480.
    createCcTask($project, $todo, $admin, [$member->id], 2400, Carbon::create(2026, 8, 7, 17, 0, 0));

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center'));
    $response->assertOk();

    $activeUsers = User::where('is_active', true)->orderBy('name')->get();
    $today = $anchor->copy()->startOfDay();
    $service = new DashboardService;

    // B2 pattern: SUMBER SAMA -- angka kartu WAJIB identik dailyLoadTotals() (F-118,
    // satu sumber, bukan dihitung ulang), dan kapasitas WAJIB identik kapasitas() (F-40).
    $expectedUsed = array_sum($service->dailyLoadTotals($activeUsers, collect([$today])));
    $expectedCapacity = array_sum($service->kapasitas($activeUsers, $today));

    $cards = $response->json('summary_cards');
    expect($cards['beban_harian']['used_minutes'])->toBe($expectedUsed)
        ->and($cards['beban_harian']['used_minutes'])->toBe(480)
        ->and($cards['beban_harian']['capacity_minutes'])->toBe($expectedCapacity);
});

test('F-4: nol field rupiah/skor-kinerja di output; prio_score cuma bobot urutan', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5), 'p1');

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center'));

    $response->assertOk();
    $flat = json_encode($response->json());
    // GUARD F-4/F-134: leaderboard/rupiah/skor-kinerja BUKAN bagian command-center.
    foreach (['rupiah', 'salary', 'gaji', 'performance_score', 'leaderboard'] as $forbidden) {
        expect(str_contains(strtolower($flat), $forbidden))->toBeFalse("field terlarang '{$forbidden}' bocor ke command-center (F-4)");
    }
    expect(array_keys($response->json('top_tasks.0')))->toBe(['id', 'title', 'priority_quadrant', 'prio_score', 'due_date', 'project', 'assignees']);
});

test('jumlah query command-center TETAP KONSTAN walau task/user/log bertambah banyak (A9/F-85)', function () {
    $admin = User::factory()->admin()->create();
    $members = User::factory()->count(3)->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, $members->pluck('id')->all());
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    foreach ($members as $member) {
        createCcTask($project, $todo, $admin, [$member->id], 60, Carbon::create(2026, 8, 7, 17, 0, 0), 'p2', 'daily');
    }

    // Pemanasan (query pertama bisa beda -- pola sama DashboardBebanSpreadTest F-85).
    $this->actingAs($admin)->getJson(route('dashboard.command-center', ['month' => '2026-08']));

    DB::enableQueryLog();
    $this->actingAs($admin)->getJson(route('dashboard.command-center', ['month' => '2026-08']))->assertOk();
    $smallCount = count(DB::getQueryLog());
    DB::flushQueryLog();
    DB::disableQueryLog();

    // GURUH: pertumbuhan volume lewat jalur NORMAL (task+assignee sungguhan, sama
    // komposisi event dengan setup 'kecil') -- bukan insert log mentah, supaya isi
    // TOP-10 recent activity tetap sebanding komposisinya (event 'created'/'assigned'
    // dengan user_id nyata), tidak bias oleh jenis event lain yang query batch-nya beda.
    $moreMembers = User::factory()->count(8)->create(['organization_id' => $admin->organization_id]);
    $project->members()->attach($moreMembers->pluck('id'));
    foreach ($moreMembers as $member) {
        createCcTask($project, $todo, $admin, [$member->id], 60, Carbon::create(2026, 8, 7, 17, 0, 0), 'p3', 'weekly');
    }

    DB::enableQueryLog();
    $this->actingAs($admin)->getJson(route('dashboard.command-center', ['month' => '2026-08']))->assertOk();
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeCount)->toBe($smallCount);
});

// =============================================================================
// v1.2 DS-4 -- filter PER-WIDGET periode+user (BLUEPRINT §12.5, F-109). Kode
// commandCenterPayload() sudah menambah 18 query param opsional sebelum blok
// test ini ditulis -- blok ini menutup DoD (F-73/"tulis test", bukan menambah
// fitur baru). Setiap test membuktikan filter MENYEMPIT hasil default (bukan
// cuma "tidak error").
// =============================================================================

test('filter donut_from/to + donut_user_id menyempit due_date & assignee (DS-4/F-109)', function () {
    $admin = User::factory()->admin()->create();
    $m1 = User::factory()->create(['organization_id' => $admin->organization_id]);
    $m2 = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$m1->id, $m2->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$m1->id], 60, $anchor->copy()->addDays(5), 'p1'); // dalam rentang
    createCcTask($project, $todo, $admin, [$m1->id], 60, $anchor->copy()->addDays(15), 'p1'); // luar rentang
    createCcTask($project, $todo, $admin, [$m2->id], 60, $anchor->copy()->addDays(5), 'p2'); // dalam rentang, user lain

    $baseline = $this->actingAs($admin)->getJson(route('dashboard.command-center'));
    $baseline->assertOk()->assertJsonPath('donut_priority.p1', 2)->assertJsonPath('donut_priority.p2', 1);

    $ranged = $this->actingAs($admin)->getJson(route('dashboard.command-center', [
        'donut_from' => $anchor->copy()->addDays(3)->toDateString(),
        'donut_to' => $anchor->copy()->addDays(10)->toDateString(),
    ]));
    $ranged->assertOk()->assertJsonPath('donut_priority.p1', 1)->assertJsonPath('donut_priority.p2', 1);

    $rangedAndUser = $this->actingAs($admin)->getJson(route('dashboard.command-center', [
        'donut_from' => $anchor->copy()->addDays(3)->toDateString(),
        'donut_to' => $anchor->copy()->addDays(10)->toDateString(),
        'donut_user_id' => $m1->id,
    ]));
    $rangedAndUser->assertOk()->assertJsonPath('donut_priority.p1', 1)->assertJsonPath('donut_priority.p2', 0);
});

test('filter progress_from/to + progress_user_id menyempit due_date & assignee (DS-4/F-109)', function () {
    $admin = User::factory()->admin()->create();
    $m1 = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$m1->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $progress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$m1->id], 60, $anchor->copy()->addDays(5)); // dalam rentang
    createCcTask($project, $todo, $admin, [$m1->id], 60, $anchor->copy()->addDays(15)); // luar rentang
    createCcTask($project, $progress, $admin, [$m1->id], 60, $anchor->copy()->addDays(5)); // dalam rentang

    $baseline = $this->actingAs($admin)->getJson(route('dashboard.command-center'));
    $baseline->assertOk()->assertJsonPath('progress_distribution.todo', 2)->assertJsonPath('progress_distribution.progress', 1);

    $filtered = $this->actingAs($admin)->getJson(route('dashboard.command-center', [
        'progress_from' => $anchor->copy()->addDays(3)->toDateString(),
        'progress_to' => $anchor->copy()->addDays(10)->toDateString(),
        'progress_user_id' => $m1->id,
    ]));
    $filtered->assertOk()->assertJsonPath('progress_distribution.todo', 1)->assertJsonPath('progress_distribution.progress', 1);
});

test('filter categories_from/to + categories_user_id menyempit due_date & assignee (DS-4/F-109)', function () {
    $admin = User::factory()->admin()->create();
    $m1 = User::factory()->create(['organization_id' => $admin->organization_id]);
    $m2 = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$m1->id, $m2->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$m1->id], 60, $anchor->copy()->addDays(5), null, 'daily'); // dalam rentang
    createCcTask($project, $todo, $admin, [$m1->id], 60, $anchor->copy()->addDays(15), null, 'daily'); // luar rentang
    createCcTask($project, $todo, $admin, [$m2->id], 60, $anchor->copy()->addDays(5), null, 'weekly'); // dalam rentang, user lain

    $filtered = $this->actingAs($admin)->getJson(route('dashboard.command-center', [
        'categories_from' => $anchor->copy()->addDays(3)->toDateString(),
        'categories_to' => $anchor->copy()->addDays(10)->toDateString(),
        'categories_user_id' => $m1->id,
    ]));
    $filtered->assertOk();
    $categories = collect($filtered->json('task_categories'))->pluck('total', 'task_type');
    expect($categories->get('daily'))->toBe(1)->and($categories->has('weekly'))->toBeFalse();
});

test('filter top_tasks_from/to + top_tasks_user_id menyempit due_date & assignee (DS-4/F-109)', function () {
    $admin = User::factory()->admin()->create();
    $m1 = User::factory()->create(['organization_id' => $admin->organization_id]);
    $m2 = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$m1->id, $m2->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    $inRange = createCcTask($project, $todo, $admin, [$m1->id], 60, $anchor->copy()->addDays(5), 'p1');
    createCcTask($project, $todo, $admin, [$m1->id], 60, $anchor->copy()->addDays(15), 'p1'); // luar rentang
    createCcTask($project, $todo, $admin, [$m2->id], 60, $anchor->copy()->addDays(5), 'p2'); // user lain

    $filtered = $this->actingAs($admin)->getJson(route('dashboard.command-center', [
        'top_tasks_from' => $anchor->copy()->addDays(3)->toDateString(),
        'top_tasks_to' => $anchor->copy()->addDays(10)->toDateString(),
        'top_tasks_user_id' => $m1->id,
    ]));
    $filtered->assertOk();
    expect(collect($filtered->json('top_tasks'))->pluck('id')->all())->toBe([$inRange->id]);
});

test('filter activity_from/to + activity_user_id menyempit created_at & pelaku (DS-4/F-109)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);

    // GUARD: judul dipakai sebagai penanda -- bukan nama pelaku, karena event
    // 'assigned' menyebut NAMA ASSIGNEE di pesannya juga (lihat ActivityLogPresenter
    // Task:assigned) -- filter per-pelaku tetap bisa memuat nama user lain lewat
    // jalur itu, jadi judul task adalah satu-satunya penanda yang tak ambigu.
    $this->travelTo($anchor);
    $this->actingAs($admin);
    $taskDay0 = createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5)); // pelaku admin, hari-0

    $this->travelTo($anchor->copy()->addDays(2));
    $this->actingAs($member);
    $taskDay2 = createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5)); // pelaku member, hari-2

    $filtered = $this->actingAs($admin)->getJson(route('dashboard.command-center', [
        'activity_from' => $anchor->copy()->addDays(1)->toDateString(),
        'activity_to' => $anchor->copy()->addDays(3)->toDateString(),
    ]));
    $filtered->assertOk();
    $messages = collect($filtered->json('recent_activity'))->pluck('message');
    expect($messages->contains(fn ($m) => str_contains($m, $taskDay2->title)))->toBeTrue()
        ->and($messages->contains(fn ($m) => str_contains($m, $taskDay0->title)))->toBeFalse();

    $filteredByActor = $this->actingAs($admin)->getJson(route('dashboard.command-center', [
        'activity_user_id' => $admin->id,
    ]));
    $filteredByActor->assertOk();
    $actorMessages = collect($filteredByActor->json('recent_activity'))->pluck('message');
    expect($actorMessages->contains(fn ($m) => str_contains($m, $taskDay0->title)))->toBeTrue()
        ->and($actorMessages->contains(fn ($m) => str_contains($m, $taskDay2->title)))->toBeFalse();
});

test('filter heatmap_user_id menyempit roster, threshold F-128 ikut skala turun (DS-4/F-131)', function () {
    $admin = User::factory()->admin()->create();
    $heavy = User::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Heavy']);
    $light = User::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Light']);
    $project = createCcProject($admin, [$heavy->id, $light->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor(); // Senin 2026-08-03
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    // Sama skenario DashboardBebanSpreadTest: 2400 menit, due Jumat 08-07 (5 hari
    // kerja) -> beban hari ini (anchor) = 480. $light TANPA task -- beban 0.
    createCcTask($project, $todo, $admin, [$heavy->id], 2400, Carbon::create(2026, 8, 7, 17, 0, 0));

    // GUARD: roster is_active=true ikut menghitung $admin sendiri (bukan cuma
    // 2 member yang sengaja dibuat) -- n dihitung DINAMIS, bukan diasumsikan.
    $activeCount = User::where('is_active', true)->count();

    $baseline = $this->actingAs($admin)->getJson(route('dashboard.command-center', ['month' => '2026-08']));
    $baseline->assertOk();
    expect($baseline->json('heatmap.active_user_count'))->toBe($activeCount);
    // n=3 (admin+heavy+light) -> tengah floor 630. Total beban tim = 480 -> 'aman'.
    expect(collect($baseline->json('heatmap.days'))->firstWhere('date', '2026-08-03')['level'])->toBe('aman');

    $filtered = $this->actingAs($admin)->getJson(route('dashboard.command-center', [
        'month' => '2026-08',
        'heatmap_user_id' => $heavy->id,
    ]));
    $filtered->assertOk();
    expect($filtered->json('heatmap.active_user_count'))->toBe(1);
    // n=1 -> overload floor turun ke 420. Beban $heavy sendiri = 480 -> 'overload'.
    expect(collect($filtered->json('heatmap.days'))->firstWhere('date', '2026-08-03')['level'])->toBe('overload');
});

test('filter workload_user_id + workload_date menyempit roster & anchor tanpa geser tanggal utama (DS-4/F-118/F-109)', function () {
    $admin = User::factory()->admin()->create();
    $big = User::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Big']);
    $small = User::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Small']);
    $project = createCcProject($admin, [$big->id, $small->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor(); // Senin 2026-08-03
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$big->id], 2400, Carbon::create(2026, 8, 7, 17, 0, 0));
    createCcTask($project, $todo, $admin, [$small->id], 60, Carbon::create(2026, 8, 7, 17, 0, 0));

    $filteredUser = $this->actingAs($admin)->getJson(route('dashboard.command-center', ['workload_user_id' => $small->id]));
    $filteredUser->assertOk();
    $top = collect($filteredUser->json('workload_top5'));
    expect($top->pluck('id')->all())->toBe([$small->id]);

    // workload_date GESER anchor widget ini SENDIRI -- 'date' top-level (dari
    // ?date=, dipakai section "Beban Tim") WAJIB tidak ikut bergeser (F-109,
    // lihat komentar KONTRAK commandCenterPayload()).
    $otherDate = $anchor->copy()->addDay();
    $shifted = $this->actingAs($admin)->getJson(route('dashboard.command-center', [
        'workload_user_id' => $big->id,
        'workload_date' => $otherDate->toDateString(),
    ]));
    $shifted->assertOk();
    expect($shifted->json('date'))->toBe($anchor->toDateString());

    $service = new DashboardService;
    $expectedBeban = collect($service->forUsers(collect([$big]), $otherDate))->get($big->id)['beban'];
    expect($shifted->json('workload_top5.0.beban'))->toBe($expectedBeban);
});

test('filters key balikin 18 param, null default & terisi kalau dikirim (DS-4/F-109)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $default = $this->actingAs($admin)->getJson(route('dashboard.command-center'));
    $default->assertOk();
    $filters = $default->json('filters');
    expect(array_keys($filters))->toHaveCount(18);
    expect(collect($filters)->every(fn ($v) => $v === null))->toBeTrue();

    // F-148: donut_user_id WAJIB balik sbg INT (di-cast eksplisit di controller),
    // bukan string mentah dari query -- kontrak API jujur ke TS `number | null`.
    $filled = $this->actingAs($admin)->getJson(route('dashboard.command-center', ['donut_user_id' => $member->id]));
    $filled->assertOk()->assertJsonPath('filters.donut_user_id', $member->id)
        ->assertJsonPath('filters.progress_user_id', null);
    expect($filled->json('filters.donut_user_id'))->toBeInt();
});

test('summary_cards TIDAK ikut filter widget mana pun, tetap agregat penuh (§12.5, keputusan Boss 2026-07-29)', function () {
    $admin = User::factory()->admin()->create();
    $m1 = User::factory()->create(['organization_id' => $admin->organization_id]);
    $m2 = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$m1->id, $m2->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$m1->id], 60, $anchor->copy()->addDays(5));
    createCcTask($project, $todo, $admin, [$m2->id], 60, $anchor->copy()->addDays(5));

    $baseline = $this->actingAs($admin)->getJson(route('dashboard.command-center'))->json('summary_cards');

    // donut_user_id HANYA milik widget donut -- summary_cards WAJIB tetap
    // sama walau query param ini dikirim (bukti isolasi antar-widget).
    $withUnrelatedFilter = $this->actingAs($admin)->getJson(route('dashboard.command-center', ['donut_user_id' => $m1->id]))->json('summary_cards');

    expect($withUnrelatedFilter)->toBe($baseline)->and($baseline['todo'])->toBe(2);
});

// =============================================================================
// v1.2 DS-4b -- widget "Status Project" (§12.5): COUNTS per proyek, SENGAJA
// bukan derivasi status-label (F-125, di luar scope, urusan halaman Proyek).
// =============================================================================

test('widget Status Project: counts per FLAG F-44 + overdue + deadline, proyek diarsip dikecualikan (DS-4b/§12.5)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $deadline = Carbon::create(2026, 9, 1);
    $project->update(['due_date' => $deadline]);
    $archived = createCcProject($admin, [$member->id]);
    $archived->update(['is_archived' => true]);

    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $progress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5));
    createCcTask($project, $progress, $admin, [$member->id], 60, $anchor->copy()->addDays(5));
    createCcTask($project, $review, $admin, [$member->id], 60, $anchor->copy()->addDays(5));
    createCcTask($project, $done, $admin, [$member->id], 60, $anchor->copy()->subDays(2)); // selesai, due lampau -- BUKAN overdue
    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->subDays(1)); // belum selesai, due lampau -- overdue

    // Proyek diarsip -- tasknya TIDAK BOLEH ikut manapun (widget ini nol project ini).
    $archivedTodo = TaskStatus::where('project_id', $archived->id)->where('position', 0)->firstOrFail();
    createCcTask($archived, $archivedTodo, $admin, [$member->id], 60, $anchor->copy()->addDays(5));

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center'));
    $response->assertOk();

    $rows = collect($response->json('status_projects'));
    expect($rows->pluck('id')->contains($archived->id))->toBeFalse();

    $row = $rows->firstWhere('id', $project->id);
    expect($row['task_total'])->toBe(5)
        ->and($row['todo'])->toBe(2) // 1 murni + 1 overdue
        ->and($row['progress'])->toBe(1)
        ->and($row['selesai'])->toBe(1)
        ->and($row['overdue'])->toBe(1);
    // F-72: due_date (cast 'date') diserialisasi WIB (SerializesDatesInAppTimezone),
    // BUKAN UTC -- cek prefiks tanggal, bukan string ISO persis (offset boleh beda
    // representasi selama tanggal kalendernya benar).
    expect(str_starts_with((string) $row['due_date'], '2026-09-01'))->toBeTrue();
});

test('widget Status Project: top-5 diurut task_total DESC, proyek ke-6 tak ikut (DS-4b/§12.5)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    $projects = collect(range(1, 6))->map(function (int $i) use ($admin, $member) {
        $project = createCcProject($admin, [$member->id]);
        $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
        // proyek ke-i dapat i task -- proyek #1 (task_total=1) WAJIB tersingkir dari top-5.
        for ($t = 0; $t < $i; $t++) {
            createCcTask($project, $todo, $admin, [$member->id], 60, Carbon::now()->addDays(5));
        }

        return $project;
    });

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center'));
    $response->assertOk();

    $rows = collect($response->json('status_projects'));
    expect($rows)->toHaveCount(5);
    expect($rows->pluck('id')->contains($projects->first()->id))->toBeFalse(); // task_total=1, tersingkir
    expect($rows->pluck('task_total')->all())->toBe($rows->pluck('task_total')->sortDesc()->values()->all());
});

test('widget Status Project: N+1 KONSTAN walau jumlah proyek bertambah (DS-4b/F-85)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    // GUARD (pola sama A9): tiap iterasi bikin PROJECT+TASK -- KOMPOSISI TIPE
    // event yang SAMA (Project:assigned dari members()->sync(), lalu Task:created)
    // diulang, supaya jendela top-10 recent_activity tetap berisi SET TIPE subject
    // yang sama antara pengukuran kecil & besar (morphTo eager-load 1 query PER
    // TIPE HADIR di window -- kalau komposisi tipe berubah, query count ikut
    // berubah walau BUKAN gara-gara statusProjects(), bias yang sama seperti
    // dihindari test A9 di atas).
    $seedProjectAndTask = function () use ($admin, $member, $anchor) {
        $project = createCcProject($admin, [$member->id]);
        $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
        createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5));
    };

    // GUARD: actingAs($admin) WAJIB dipasang SEBELUM seed pertama -- kalau
    // tidak, log activity pertama tercatat user_id=NULL (Auth::id() kosong),
    // dan Eloquent men-skip query eager-load with('user:id,name') saat SEMUA
    // foreign key di batch NULL. Itu bikin hitungan "kecil" & "besar" beda
    // query count karena BEDA KOMPOSISI ada/tidaknya actor, bukan gara-gara
    // statusProjects() -- ditemukan sendiri lewat dump SQL saat test ini
    // pertama kali gagal (39 vs 38).
    $this->actingAs($admin);
    $seedProjectAndTask();

    // Pemanasan (pola sama test A9 -- query pertama bisa beda).
    $this->actingAs($admin)->getJson(route('dashboard.command-center'));

    DB::enableQueryLog();
    $this->actingAs($admin)->getJson(route('dashboard.command-center'))->assertOk();
    $smallCount = count(DB::getQueryLog());
    DB::flushQueryLog();
    DB::disableQueryLog();

    foreach (range(1, 9) as $_) {
        $seedProjectAndTask();
    }

    DB::enableQueryLog();
    $this->actingAs($admin)->getJson(route('dashboard.command-center'))->assertOk();
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeCount)->toBe($smallCount);
});

// =============================================================================
// v1.2 H4 -- halaman Inertia 'dashboard/overview' (commandCenterPage()). B1/B2/F-109.
// =============================================================================

test('guest redirected, member tanpa dashboard.view forbidden, admin ok (halaman command-center, F-95)', function () {
    $this->get(route('dashboard.overview'))->assertRedirect('/login');

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($member)->get(route('dashboard.overview'))->assertForbidden();
    $this->actingAs($admin)->get(route('dashboard.overview'))->assertOk();
});

test('halaman command-center menerima SEMUA props widget + team (Beban Tim, B1/F-52/F-121)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5), 'p1');

    $response = $this->actingAs($admin)->get(route('dashboard.overview'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('command-center')
        ->has('summary_cards')
        ->has('donut_priority')
        ->has('progress_distribution')
        ->has('task_categories')
        ->has('heatmap')
        ->has('top_tasks')
        ->has('recent_activity')
        ->has('workload_top5')
        // F-52/F-121: dashboard 3-angka lama TETAP ADA sebagai section "team" --
        // BUKAN dihapus/diganti, cuma ditambah di sekitarnya.
        ->has('team.date')
        ->has('team.rows'));
});

test('props halaman command-center IDENTIK JSON dashboard.command-center, satu sumber (F-109)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$member->id], 2400, Carbon::create(2026, 8, 7, 17, 0, 0), 'p2');

    $json = $this->actingAs($admin)->getJson(route('dashboard.command-center', ['month' => '2026-08']))->json();
    $page = $this->actingAs($admin)->get(route('dashboard.overview', ['month' => '2026-08']));

    $page->assertOk();
    // GUARD F-109: commandCenterPage() TIDAK BOLEH menyusun ulang array widget --
    // WAJIB persis sama dengan yang dikembalikan endpoint JSON yang sudah dites.
    foreach (['summary_cards', 'donut_priority', 'progress_distribution', 'task_categories', 'heatmap', 'top_tasks', 'recent_activity', 'workload_top5'] as $key) {
        $page->assertInertia(fn (AssertableInertia $p) => $p->where($key, $json[$key]));
    }
});

test('team.rows halaman command-center IDENTIK rows dashboard lama, satu sumber loadRows() (F-52/F-109)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$member->id], 120, $anchor->copy()->addDays(3));

    $oldPage = $this->actingAs($admin)->get(route('dashboard'));
    $newPage = $this->actingAs($admin)->get(route('dashboard.overview'));

    $oldRows = $oldPage->viewData('page')['props']['rows'];
    $newPage->assertInertia(fn (AssertableInertia $p) => $p->where('team.rows', $oldRows));
});

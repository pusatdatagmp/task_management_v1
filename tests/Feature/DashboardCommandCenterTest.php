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

use App\Models\Holiday;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\DashboardService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/**
 * KONTRAK: revisi 2026-08-06 -- viewer Command Center dengan dashboard.view TAPI
 * TANPA project.viewAll (custom role, BUKAN admin). Dipakai membuktikan restriksi
 * "cuma lihat data sendiri" (F-90, reuse project.viewAll sebagai basis pembeda).
 */
function ccRestrictedViewer(User $orgSeed, bool $withProjectViewAll = false): User
{
    $role = Role::create([
        'organization_id' => $orgSeed->organization_id,
        'role_name' => 'CC Restricted '.uniqid(),
        'is_system' => false,
        'is_default' => false,
    ]);

    $permissionNames = $withProjectViewAll ? ['dashboard.view', 'project.viewAll'] : ['dashboard.view'];
    $role->permissions()->attach(Permission::whereIn('permission_name', $permissionNames)->pluck('id'));

    return User::factory()->create(['organization_id' => $orgSeed->organization_id, 'role_id' => $role->id]);
}

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

function createCcTask(Project $project, TaskStatus $status, User $admin, array $assigneeIds, int $estimatedMinutes, Carbon $dueDate, ?string $priorityQuadrant = null, string $taskType = 'tentative', ?int $taskTemplateId = null): Task
{
    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'CC task '.uniqid(),
        'task_type' => $taskType,
        'task_template_id' => $taskTemplateId,
        'priority_quadrant' => $priorityQuadrant,
        'estimated_minutes' => $estimatedMinutes,
        'due_date' => $dueDate,
        'created_by' => $admin->id,
    ]);

    $task->assignees()->sync($assigneeIds);

    return $task;
}

// Revisi 2026-08-07 (permintaan Boss, iterasi ke-2): widget "Kategori Tugas
// Berulang" (A4) sekarang daftar PER TEMPLATE (nama, schedule_label, jumlah
// task ALL-TIME) -- helper ini isi anchor_strategy=time_based+interval supaya
// TaskTemplate::scheduleLabel() (AE-2b) menghasilkan teks yang bisa dites,
// mis. "Tiap 3 hari". task_type/recurrence_config diisi konstan (dead-tapi-
// aman, lihat TaskTemplateController::store()) -- kolomnya NOT NULL di DB.
function createCcTemplate(Project $project, User $admin, int $intervalValue = 1, string $intervalUnit = 'day'): TaskTemplate
{
    return TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'CC template '.uniqid(),
        'task_type' => 'daily',
        'estimated_minutes' => 60,
        'points' => 1,
        'recurrence_config' => [],
        'default_assignees' => [],
        'is_active' => true,
        'anchor_strategy' => 'time_based',
        'interval_value' => $intervalValue,
        'interval_unit' => $intervalUnit,
    ]);
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

test('kategori tugas berulang: daftar PER TEMPLATE (nama+jadwal+jumlah ALL-TIME), HANYA task hasil generate (A4/revisi 2026-08-07)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    $everyThreeDays = createCcTemplate($project, $admin, 3, 'day');
    $everyTwoWeeks = createCcTemplate($project, $admin, 2, 'week');
    // Iterasi ke-3 ("template saya ada 2 kok cuma 1 tampil"): template AKTIF
    // tapi belum pernah di-generate (0 task) WAJIB tetap tampil untuk viewer
    // PENUH (admin) -- BEDA dari perilaku lama yang menyembunyikannya.
    $neverGenerated = createCcTemplate($project, $admin, 5, 'day');

    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5), null, 'daily', $everyThreeDays->id);
    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(50), null, 'daily', $everyThreeDays->id); // jauh di luar "hari ini" -- BUKTI jumlah ALL-TIME, bukan ter-filter periode
    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5), null, 'daily', $everyTwoWeeks->id);
    // GUARD: task manual (bukan hasil generate, task_template_id null) TIDAK
    // boleh ikut dihitung -- ini yang membedakan widget baru dari versi lama.
    createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5), null, 'project');

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center'));

    $response->assertOk();
    $categories = collect($response->json('task_categories'))->keyBy('id');
    expect($categories[$everyThreeDays->id]['title'])->toBe($everyThreeDays->title)
        ->and($categories[$everyThreeDays->id]['schedule_label'])->toBe('Tiap 3 hari')
        ->and($categories[$everyThreeDays->id]['total'])->toBe(2)
        ->and($categories[$everyTwoWeeks->id]['schedule_label'])->toBe('Tiap 2 minggu')
        ->and($categories[$everyTwoWeeks->id]['total'])->toBe(1)
        ->and($categories[$neverGenerated->id]['total'])->toBe(0)
        ->and($categories)->toHaveCount(3);
});

test('kategori tugas berulang: dibatasi TOP-5 by total DESC, biar layout widget tidak melar (revisi 2026-08-13)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    // 7 template AKTIF, jumlah task ALL-TIME beda-beda (7..1) -- WAJIB cuma
    // 5 TERBESAR yang tampil di widget, sisanya (total 2 & 1) TIDAK ikut.
    $templates = [];
    foreach (range(7, 1, -1) as $total) {
        $template = createCcTemplate($project, $admin, $total, 'day');
        for ($i = 0; $i < $total; $i++) {
            createCcTask($project, $todo, $admin, [$member->id], 60, $anchor->copy()->addDays(5 + $i), null, 'daily', $template->id);
        }
        $templates[$total] = $template;
    }

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center'));

    $response->assertOk();
    $categories = collect($response->json('task_categories'));

    expect($categories)->toHaveCount(5)
        ->and($categories->pluck('total')->all())->toBe([7, 6, 5, 4, 3]);

    $shownIds = $categories->pluck('id')->all();
    expect($shownIds)->not->toContain($templates[2]->id)
        ->and($shownIds)->not->toContain($templates[1]->id);
});

test('kategori tugas berulang: viewer TERBATAS TIDAK lihat template ber-jumlah-0 (privasi, beda dari admin) (revisi 2026-08-07)', function () {
    $admin = User::factory()->admin()->create();
    $viewer = ccRestrictedViewer($admin);
    $project = createCcProject($admin, [$viewer->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    $withTask = createCcTemplate($project, $admin, 1, 'day');
    $withoutTask = createCcTemplate($project, $admin, 1, 'week');
    createCcTask($project, $todo, $admin, [$viewer->id], 60, $anchor->copy()->addDays(5), null, 'daily', $withTask->id);

    $response = $this->actingAs($viewer)->getJson(route('dashboard.command-center'));

    $response->assertOk();
    $categories = collect($response->json('task_categories'))->keyBy('id');
    expect($categories->has($withTask->id))->toBeTrue()
        ->and($categories->has($withoutTask->id))->toBeFalse();
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

test('heatmap: task OVERDUE tidak lagi menempel PENUH ke SETIAP hari termasuk bulan depan (F-170, audit Boss 2026-08-13)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = ccAnchor(); // Senin 2026-08-03
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    // Task OVERDUE: due_date jauh SEBELUM anchor, 500 menit, 1 assignee. SEBELUM
    // fix F-170, dailyLoadTotals() menempel beban PENUH task ini ULANG di SETIAP
    // tanggal yang dicek -- termasuk seluruh hari bulan depan (September), walau
    // task ini sama sekali tidak "milik" bulan depan (bug yang dilaporkan Boss).
    createCcTask($project, $todo, $admin, [$member->id], 500, Carbon::create(2026, 7, 1, 17, 0, 0));

    // Bulan SEKARANG (Agustus): HARI INI (anchor) tetap WAJIB menanggung beban
    // PENUH task overdue ini -- task itu memang belum selesai, wajar membebani
    // hari ini (F-96a A3, TIDAK berubah oleh fix).
    $augustResponse = $this->actingAs($admin)->getJson(route('dashboard.command-center', ['month' => '2026-08']));
    $augustResponse->assertOk();
    $augustDays = collect($augustResponse->json('heatmap.days'));
    $todayEntry = $augustDays->firstWhere('date', '2026-08-03');
    expect($todayEntry['beban'])->toBe(500);

    // Tanggal LAIN di bulan Agustus (bukan hari ini) TIDAK BOLEH lagi menanggung
    // task overdue yang sama -- ini bagian dari F-170, dulu numpuk di SEMUA tanggal.
    $otherAugustEntry = $augustDays->firstWhere('date', '2026-08-04');
    expect($otherAugustEntry['beban'])->toBe(0)->and($otherAugustEntry['level'])->toBe('aman');

    // Bulan DEPAN (September): sama sekali tidak ada task nyata di bulan itu, jadi
    // WAJIB bersih (beban 0, level 'aman') di SEMUA hari -- ini persis skenario
    // yang dilaporkan Boss: kalender bulan depan kelihatan overload walau kosong.
    $septResponse = $this->actingAs($admin)->getJson(route('dashboard.command-center', ['month' => '2026-09']));
    $septResponse->assertOk();
    $septDays = collect($septResponse->json('heatmap.days'));

    foreach ($septDays as $day) {
        expect($day['beban'])->toBe(0);
        expect($day['level'])->toBe('aman');
    }
});

test('hari Minggu OTOMATIS ikon libur + label "Hari Minggu", TANPA baris Holiday manual (permintaan Boss 2026-08-10)', function () {
    $admin = User::factory()->admin()->create();
    $anchor = ccAnchor(); // Senin 2026-08-03
    seedCcSchedule($admin, $anchor); // Mon-Fri saja -- Minggu BUKAN hari kerja di WorkSchedule ini.
    $this->travelTo($anchor);

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center', ['month' => '2026-08']));
    $response->assertOk();

    $days = collect($response->json('heatmap.days'));

    // 2026-08-09 = Minggu, TIDAK ada baris Holiday manual sama sekali di DB.
    $sunday = $days->firstWhere('date', '2026-08-09');
    expect($sunday['type'])->toBe('libur')
        ->and($sunday['holiday'])->toBe('Hari Minggu');

    // GUARD: MURNI ikon tampilan -- beban/level TETAP dihitung normal (tidak
    // di-null-kan/diperlakukan khusus), tunduk WorkSchedule.days_of_week apa
    // adanya (Minggu bukan hari kerja di schedule ini, jadi task manapun TIDAK
    // menyumbang beban ke hari ini -- tapi itu KARENA WorkSchedule, BUKAN karena
    // flag 'libur' ikon ini).
    expect($sunday['level'])->not->toBeNull()
        ->and($sunday['beban'])->not->toBeNull();
});

test('libur manual (Holiday DB) yang KEBETULAN jatuh hari Minggu TETAP menang -- nama asli, bukan "Hari Minggu" generik', function () {
    $admin = User::factory()->admin()->create();
    $anchor = ccAnchor(); // Senin 2026-08-03
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    // 2026-08-16 = Minggu -- pasang Holiday manual bernama di tanggal itu.
    Holiday::create([
        'organization_id' => $admin->organization_id,
        'date' => '2026-08-16',
        'name' => 'Libur Khusus Perusahaan',
    ]);

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center', ['month' => '2026-08']));
    $response->assertOk();

    $entry = collect($response->json('heatmap.days'))->firstWhere('date', '2026-08-16');
    expect($entry['type'])->toBe('libur')
        ->and($entry['holiday'])->toBe('Libur Khusus Perusahaan'); // BUKAN "Hari Minggu" -- manual menang.
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
        ->and($cards['selesai'])->toBe(1) // kartu baru 2026-08-08 (permintaan Boss)
        ->and($cards['overdue'])->toBe(2)
        ->and(array_keys($cards))->toBe(['beban_harian', 'todo', 'in_progress', 'review', 'selesai', 'overdue']);
});

test('summary cards: beban_harian TIDAK lagi ikut estimasi rencana (F-118) -- task belum dikerjakan sama sekali = used_minutes 0 (revisi 2026-08-15)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = ccAnchor(); // Senin 2026-08-03
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    // Task besar (2400 menit, due 5 hari kerja lagi) TAPI nol timeSegments --
    // dulu (F-118) ini bikin beban_harian.used_minutes=480 (estimasi disebar).
    // Sekarang kartu ini REALISASI: belum ada satu menit pun benar-benar
    // dikerjakan -> used_minutes WAJIB 0, walau backlog rencana besar.
    createCcTask($project, $todo, $admin, [$member->id], 2400, Carbon::create(2026, 8, 7, 17, 0, 0));

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center'));
    $response->assertOk();

    $activeUsers = User::where('is_active', true)->orderBy('name')->get();
    $today = $anchor->copy()->startOfDay();
    $service = new DashboardService;

    // B2 pattern: SUMBER SAMA -- angka kartu WAJIB identik forUsers() (F-52,
    // kapasitas - idle_real = realisasi total), bukan dihitung ulang.
    $rows = $service->forUsers($activeUsers, $today);
    $expectedUsed = array_sum(array_map(fn (array $r) => $r['kapasitas'] - $r['idle_real'], $rows));
    $expectedCapacity = array_sum(array_column($rows, 'kapasitas'));

    $cards = $response->json('summary_cards');
    expect($cards['beban_harian']['used_minutes'])->toBe($expectedUsed)
        ->and($cards['beban_harian']['used_minutes'])->toBe(0)
        ->and($cards['beban_harian']['capacity_minutes'])->toBe($expectedCapacity);
});

test('summary cards: beban_harian.used_minutes = realisasi BENAR-BENAR dikerjakan (segmen terbuka), IDENTIK kolom Kapasitas Sisa (revisi 2026-08-15 permintaan Boss)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$member->id]);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $anchor = ccAnchor(); // Senin 2026-08-03, 09:00
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    // Estimasi/due SENGAJA jauh (2400 menit, due 5 hari kerja lagi) supaya
    // kalau beban_harian DIAM-DIAM masih ikut F-118 (regresi), test ini
    // ketahuan gagal (bakal 480, bukan 50).
    $task = createCcTask($project, $inProgress, $admin, [$member->id], 2400, Carbon::create(2026, 8, 7, 17, 0, 0));
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $member->id,
        'started_at' => $anchor->copy(),
    ]);

    $this->travelTo($anchor->copy()->addMinutes(50));

    $response = $this->actingAs($admin)->getJson(route('dashboard.command-center'));
    $response->assertOk();

    $activeUsers = User::where('is_active', true)->orderBy('name')->get();
    $today = Carbon::now()->startOfDay();
    $service = new DashboardService;
    $rows = $service->forUsers($activeUsers, $today);
    $expectedUsed = array_sum(array_map(fn (array $r) => $r['kapasitas'] - $r['idle_real'], $rows));

    $cards = $response->json('summary_cards');
    expect($cards['beban_harian']['used_minutes'])->toBe($expectedUsed)
        ->and($cards['beban_harian']['used_minutes'])->toBe(50);
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
    // Permintaan Boss: tabel Top-10 di FE butuh kolom Kategori (task_type) & Status
    // (nama/warna task_status ASLI, F-44) -- 2 key ditambah di sini (F-78: cakupan
    // setara, TETAP menjaga guard F-4 di atas -- status.name/color bukan
    // rupiah/skor-kinerja). `project_id` (F-78 lanjutan) -- judul task di FE
    // sekarang Link ke route('tasks.show', [project_id, id]), butuh id numerik
    // (nama project doang tidak cukup untuk bangun URL).
    expect(array_keys($response->json('top_tasks.0')))->toBe([
        'id', 'title', 'priority_quadrant', 'prio_score', 'due_date', 'project', 'project_id', 'assignees', 'task_type', 'status',
    ]);
});

test('jumlah query command-center TETAP KONSTAN walau task/user/log bertambah banyak (A9/F-85)', function () {
    $admin = User::factory()->admin()->create();
    $members = User::factory()->count(3)->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, $members->pluck('id')->all());
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    // GUARD (F-78, ditemukan H9): actingAs() WAJIB sebelum SEMUA penulisan data,
    // termasuk batch 'kecil' di bawah -- Auth::id() dipakai LogsActivity sebagai
    // activity_logs.user_id. Sebelum fix ini, 3 task awal ditulis TANPA actingAs()
    // -> user_id NULL utk seluruhnya -> ActivityLogPresenter batch query 'user'
    // (DashboardController::recentActivity(), with('user:id,name')) di-skip total
    // oleh Eloquent (BelongsTo eager load tidak query kalau SEMUA FK null di batch).
    // Batch 'besar' ditulis SETELAH actingAs() pertama (baris di bawah) -> user_id
    // terisi -> query itu MUNCUL -> beda 41 vs 42 padahal BUKAN N+1 yang tumbuh
    // dengan volume data, cuma efek unauthenticated-vs-authenticated yang tak
    // sengaja ikut berubah di antara dua pengukuran. actingAs() di sini menyamakan
    // kondisi KEDUA batch supaya satu-satunya variabel yang berubah = volume data.
    $this->actingAs($admin);

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

test('filter categories_user_id menyempit ke assignee itu SAJA, jumlah TETAP ALL-TIME (DS-4/F-109/revisi 2026-08-07)', function () {
    $admin = User::factory()->admin()->create();
    $m1 = User::factory()->create(['organization_id' => $admin->organization_id]);
    $m2 = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$m1->id, $m2->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    $dailyTemplate = createCcTemplate($project, $admin, 1, 'day');
    $weeklyTemplate = createCcTemplate($project, $admin, 1, 'week');

    createCcTask($project, $todo, $admin, [$m1->id], 60, $anchor->copy()->addDays(5), null, 'daily', $dailyTemplate->id);
    createCcTask($project, $todo, $admin, [$m1->id], 60, $anchor->copy()->addDays(50), null, 'daily', $dailyTemplate->id); // jauh, TETAP kehitung (all-time)
    createCcTask($project, $todo, $admin, [$m2->id], 60, $anchor->copy()->addDays(5), null, 'weekly', $weeklyTemplate->id); // assignee lain

    $filtered = $this->actingAs($admin)->getJson(route('dashboard.command-center', [
        'categories_user_id' => $m1->id,
    ]));
    $filtered->assertOk();
    $categories = collect($filtered->json('task_categories'))->keyBy('id');
    // Revisi 2026-08-07 (iterasi ke-3, "template saya ada 2 kok cuma 1 tampil"):
    // admin (viewer PENUH, bukan restrictToSelf) TETAP lihat weeklyTemplate
    // walau jumlah-nya 0 untuk filter m1 -- BEDA dari viewer terbatas yang
    // template ber-jumlah-0 disembunyikan (lihat test restrictToSelf di bawah).
    expect($categories[$dailyTemplate->id]['total'])->toBe(2)
        ->and($categories[$weeklyTemplate->id]['total'])->toBe(0);
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

test('filters key balikin 16 param, null default & terisi kalau dikirim (DS-4/F-109/revisi 2026-08-07)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $default = $this->actingAs($admin)->getJson(route('dashboard.command-center'));
    $default->assertOk();
    $filters = $default->json('filters');
    // Revisi 2026-08-07: categories_from/categories_to dicabut (widget
    // "Kategori Tugas Berulang" jadi all-time, nol filter periode) -- 18 -> 16.
    expect(array_keys($filters))->toHaveCount(16);
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
        // BUG FIX (audit Boss 2026-08-07): sebelum ini kolom 'review' TIDAK ADA
        // sama sekali -- task $review di atas dibuat tapi TIDAK PERNAH dicek di
        // sini, jadi lolos test walau todo+progress+selesai (4) < task_total (5).
        // GUARD eksplisit: jumlah semua kategori WAJIB persis task_total, supaya
        // regresi "1 kategori kelupaan" seperti ini ketahuan lagi ke depannya.
        ->and($row['review'])->toBe(1)
        ->and($row['selesai'])->toBe(1)
        ->and($row['overdue'])->toBe(1)
        ->and($row['todo'] + $row['progress'] + $row['review'] + $row['selesai'])->toBe($row['task_total']);
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

// =============================================================================
// Revisi 2026-08-06 (permintaan Boss) -- viewer TANPA project.viewAll dibatasi
// ke data sendiri di Command Center. Dashboard 3-angka lama TIDAK disentuh.
// =============================================================================

test('revisi 2026-08-06: viewer TANPA project.viewAll -- restricted_to_self=true, semua widget cuma data sendiri', function () {
    $admin = User::factory()->admin()->create();
    $viewer = ccRestrictedViewer($admin);
    $other = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$viewer->id, $other->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$viewer->id], 60, $anchor->copy()->addDays(2), 'p1');
    createCcTask($project, $todo, $admin, [$other->id], 60, $anchor->copy()->addDays(2), 'p2');

    $response = $this->actingAs($viewer)->getJson(route('dashboard.command-center', ['month' => '2026-08']));
    $response->assertOk();
    $json = $response->json();

    expect($json['restricted_to_self'])->toBeTrue()
        // Cuma task milik $viewer (p1) yang terhitung, task $other (p2) TIDAK.
        ->and($json['donut_priority'])->toBe(['p1' => 1, 'p2' => 0, 'p3' => 0, 'p4' => 0, 'none' => 0])
        // Widget per-proyek nol makna "punya siapa" -- kosong (keputusan Boss).
        ->and($json['status_projects'])->toBe([])
        // Dropdown filter cuma berisi DIRINYA SENDIRI.
        ->and($json['filter_users'])->toHaveCount(1)
        ->and($json['filter_users'][0]['id'])->toBe($viewer->id);

    expect(collect($json['top_tasks'])->pluck('id')->all())->toBe([
        Task::where('project_id', $project->id)->whereHas('assignees', fn ($q) => $q->whereKey($viewer->id))->value('id'),
    ]);
});

test('revisi 2026-08-06: viewer terbatas TIDAK BISA intip user lain lewat manipulasi query string (SERVER guard, bukan HINT UI)', function () {
    $admin = User::factory()->admin()->create();
    $viewer = ccRestrictedViewer($admin);
    $other = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$viewer->id, $other->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$viewer->id], 60, $anchor->copy()->addDays(2), 'p1');
    createCcTask($project, $todo, $admin, [$other->id], 60, $anchor->copy()->addDays(2), 'p3');

    // $viewer mencoba lihat data $other lewat URL manipulation -- HARUS diabaikan.
    $response = $this->actingAs($viewer)->getJson(route('dashboard.command-center', [
        'month' => '2026-08',
        'donut_user_id' => $other->id,
        'top_tasks_user_id' => $other->id,
        'activity_user_id' => $other->id,
        'heatmap_user_id' => $other->id,
        'workload_user_id' => $other->id,
    ]));

    $response->assertOk();
    $json = $response->json();

    // Tetap p1 ($viewer punya), BUKAN p3 ($other punya) -- filter query diabaikan.
    expect($json['donut_priority'])->toBe(['p1' => 1, 'p2' => 0, 'p3' => 0, 'p4' => 0, 'none' => 0])
        ->and($json['heatmap']['active_user_count'])->toBe(1)
        ->and(collect($json['workload_top5'])->pluck('id')->all())->toBe([$viewer->id]);
});

test('revisi 2026-08-06: viewer DENGAN project.viewAll (role custom, bukan admin) tetap lihat SEMUA data', function () {
    $admin = User::factory()->admin()->create();
    $fullViewer = ccRestrictedViewer($admin, withProjectViewAll: true);
    $other = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$fullViewer->id, $other->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createCcTask($project, $todo, $admin, [$fullViewer->id], 60, $anchor->copy()->addDays(2), 'p1');
    createCcTask($project, $todo, $admin, [$other->id], 60, $anchor->copy()->addDays(2), 'p2');

    $json = $this->actingAs($fullViewer)->getJson(route('dashboard.command-center', ['month' => '2026-08']))->json();

    expect($json['restricted_to_self'])->toBeFalse()
        ->and($json['donut_priority'])->toBe(['p1' => 1, 'p2' => 1, 'p3' => 0, 'p4' => 0, 'none' => 0]);
});

test('revisi 2026-08-06: Command Center Page -- Beban Tim viewer terbatas cuma dirinya, TAPI Dashboard 3-angka lama TETAP tampil semua user (restriksi cuma Command Center)', function () {
    $admin = User::factory()->admin()->create();
    $viewer = ccRestrictedViewer($admin);
    $other = User::factory()->create(['organization_id' => $admin->organization_id]);
    createCcProject($admin, [$viewer->id, $other->id]); // side effect: project+members+statuses org ini
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    // Command Center: Beban Tim (team.rows) HANYA berisi $viewer, walau
    // $viewer coba intip user lain lewat ?user_id= (SERVER guard).
    $ccPage = $this->actingAs($viewer)->get(route('dashboard.overview', ['user_id' => $other->id]));
    $ccPage->assertOk();
    $ccTeamRows = $ccPage->viewData('page')['props']['team']['rows'];
    expect($ccTeamRows)->toHaveCount(1)
        ->and($ccTeamRows[0]['id'])->toBe($viewer->id)
        ->and($ccPage->viewData('page')['props']['team']['selected_user_id'])->toBe($viewer->id);

    // Dashboard 3-angka LAMA (route terpisah) -- TIDAK direstriksi sama sekali,
    // $viewer (dashboard.view saja) tetap lihat SEMUA user (keputusan Boss).
    $oldPage = $this->actingAs($viewer)->get(route('dashboard'));
    $oldPage->assertOk();
    $oldRows = $oldPage->viewData('page')['props']['rows'];
    expect(collect($oldRows)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$admin->id, $viewer->id, $other->id])->sort()->values()->all());
});

test('revisi 2026-08-06 (Boss): summary_cards.beban_harian ANGKANYA beda -- admin agregat semua orang, viewer terbatas cuma dirinya', function () {
    $admin = User::factory()->admin()->create();
    $viewer = ccRestrictedViewer($admin);
    $others = User::factory()->count(3)->create(['organization_id' => $admin->organization_id]);
    $project = createCcProject($admin, [$viewer->id, ...$others->pluck('id')]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $anchor = ccAnchor();
    seedCcSchedule($admin, $anchor);
    $this->travelTo($anchor);

    // Revisi 2026-08-15 (Boss): beban_harian.used_minutes SEKARANG realisasi
    // (bukan estimasi F-118 lagi) -- $viewer benar-benar kerja 60 menit, TIAP
    // $other 120 menit (segmen DITUTUP hari ini, F-52), supaya beda admin
    // (agregat) vs viewer (dirinya sendiri) tetap teruji dengan metrik baru.
    // Task-nya SENGAJA TETAP status $todo (bukan in_progress) -- realisasiBreakdown()
    // query task_time_segments LANGSUNG per user_id, nol join ke status task,
    // jadi assertion 'todo' di bawah (progressDistribution, tak tersentuh
    // revisi ini) tetap valid apa adanya.
    $viewerTask = createCcTask($project, $todo, $admin, [$viewer->id], 60, $anchor->copy()->setTime(17, 0, 0));
    $viewerTask->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $viewer->id,
        'started_at' => $anchor->copy(),
        'ended_at' => $anchor->copy()->addMinutes(60),
    ]);
    foreach ($others as $other) {
        $otherTask = createCcTask($project, $todo, $admin, [$other->id], 120, $anchor->copy()->setTime(17, 0, 0));
        $otherTask->timeSegments()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $other->id,
            'started_at' => $anchor->copy(),
            'ended_at' => $anchor->copy()->addMinutes(120),
        ]);
    }

    $adminJson = $this->actingAs($admin)->getJson(route('dashboard.command-center', ['month' => '2026-08']))->json();
    $viewerJson = $this->actingAs($viewer)->getJson(route('dashboard.command-center', ['month' => '2026-08']))->json();

    // Admin: agregat SEMUA user aktif di organisasi ini (admin+viewer+3 others =
    // 5 orang x 480 menit kapasitas = 2400; used = 60+120+120+120 = 420 REALISASI).
    expect($adminJson['summary_cards']['beban_harian'])->toBe(['used_minutes' => 420, 'capacity_minutes' => 2400])
        ->and($adminJson['summary_cards']['todo'])->toBe(4);

    // Viewer terbatas: CUMA realisasi miliknya sendiri (60 menit) & kapasitas 1 orang (480).
    expect($viewerJson['summary_cards']['beban_harian'])->toBe(['used_minutes' => 60, 'capacity_minutes' => 480])
        ->and($viewerJson['summary_cards']['todo'])->toBe(1);

    // GUARD utama: angkanya BENAR-BENAR beda, bukan cuma restricted_to_self flag-nya.
    expect($adminJson['summary_cards']['beban_harian'])->not->toBe($viewerJson['summary_cards']['beban_harian']);
});

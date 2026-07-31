<?php

/**
 * ==========================================================
 * MODUL       : DashboardTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : v0.8 H2 (F-52/F-95/F-96) — rumus KAPASITAS/BEBAN/BACKLOG lewat
 *               endpoint dashboard.summary sungguhan (F-75: HTTP nyata, bukan
 *               tinker), gating permission dashboard.view. v0.8 H3 — halaman
 *               'dashboard' (Inertia) sekarang DIGERBANGI dashboard.view juga
 *               (F-78: test starter-kit 'authenticated users can visit the
 *               dashboard' DIPERBARUI, bukan ditambal — perilaku sengaja berubah
 *               oleh instruksi Boss, cakupan setara: admin 200 + member 403 di
 *               bawah). Skenario multi-assignee (F-96 inti) ada di
 *               DashboardMultiAssigneeTest.php, anomali (F-53) di
 *               DashboardAnomalyTest.php — dipisah supaya masing-masing fokus.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : DashboardController, DashboardService
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Salah di sini = admin lihat kapasitas/beban tim yang salah,
 *               keputusan assign task ikut salah (F-52: dashboard ini fondasi
 *               keputusan, bukan sekadar tampilan).
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

// F-78: pengganti 'authenticated users can visit the dashboard' (starter kit) --
// H3 menggerbangi halaman ini di belakang dashboard.view (F-95), jadi "authenticated
// user generik" bukan lagi kondisi yang benar. Cakupan setara: admin 200 (di sini)
// + member 403 (test di bawah), bukan sekadar dihapus.
test('admin with dashboard.view can visit the dashboard page', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('dashboard')->has('rows')->has('users'));
});

test('member without dashboard.view cannot visit the dashboard page (F-95)', function () {
    $member = User::factory()->create();

    $response = $this->actingAs($member)->get('/dashboard');

    $response->assertForbidden();
});

test('angka di halaman dashboard sama dengan angka DashboardService, UI tidak menghitung ulang', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDashboardProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = Carbon::create(2026, 7, 20, 9, 0, 0);
    seedDashboardWorkSchedule($admin, $anchor, 480);
    $this->travelTo($anchor);

    createDashboardTask($project, $todo, $admin, $member, $anchor->copy()->endOfDay(), 120);

    // ?user_id= (A6) dipakai supaya rows[] deterministik berisi TEPAT 1 baris,
    // tidak bergantung urutan nama fake antara admin & member.
    $response = $this->actingAs($admin)->get(route('dashboard', ['date' => $anchor->toDateString(), 'user_id' => $member->id]));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('date', $anchor->toDateString())
        ->where('selectedUserId', $member->id)
        ->where('rows.0.id', $member->id)
        ->where('rows.0.kapasitas', 480)
        ->where('rows.0.beban', 120)
        ->where('rows.0.idle_plan', 480 - 120));
});

function createDashboardProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Dashboard Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function seedDashboardWorkSchedule(User $admin, Carbon $anchor, int $capacityMinutes = 480): void
{
    WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => $anchor->copy()->subMonth()->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => $capacityMinutes,
        'created_by' => $admin->id,
    ]);
}

function createDashboardTask(Project $project, TaskStatus $status, User $admin, User $assignee, Carbon $dueDate, int $estimatedMinutes): Task
{
    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'Dashboard task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => $estimatedMinutes,
        'due_date' => $dueDate,
        'created_by' => $admin->id,
    ]);

    $task->assignees()->sync([$assignee->id]);

    return $task;
}

function dashboardUserRow(TestResponse $response, int $userId): array
{
    return collect($response->json('users'))->firstWhere('id', $userId);
}

test('member without dashboard.view is forbidden (F-95)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $response = $this->actingAs($member)->get(route('dashboard.summary'));

    $response->assertForbidden();
});

test('admin with dashboard.view can access dashboard summary', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('dashboard.summary'));

    $response->assertOk();
    expect(dashboardUserRow($response, $admin->id))->not->toBeNull();
});

test('kapasitas: user tanpa override pakai work_schedule aktif, dengan override pakai override (F-40)', function () {
    $admin = User::factory()->admin()->create();
    $memberDefault = User::factory()->create(['organization_id' => $admin->organization_id]);
    $memberOverride = User::factory()->create(['organization_id' => $admin->organization_id, 'daily_capacity_minutes' => 360]);
    createDashboardProject($admin, [$memberDefault->id, $memberOverride->id]);

    $anchor = Carbon::create(2026, 7, 20, 9, 0, 0); // Senin
    seedDashboardWorkSchedule($admin, $anchor, 480);
    $this->travelTo($anchor);

    $response = $this->actingAs($admin)->get(route('dashboard.summary', ['date' => $anchor->toDateString()]));

    $response->assertOk();
    expect(dashboardUserRow($response, $memberDefault->id)['kapasitas'])->toBe(480)
        ->and(dashboardUserRow($response, $memberOverride->id)['kapasitas'])->toBe(360);
});

// F-78: PEMBARUAN (bukan tambalan) — sebelum v1.0.1 task "masa depan" 100% masuk
// backlog, NOL beban (F-52 lama). F-59/F-118 (keputusan Boss) sengaja mengubah
// ini: SEMUA task belum selesai ikut disebar ke hari kerja sampai tenggat, jadi
// task masa depan SEKARANG ikut menyumbang sedikit ke beban hari ini juga (bukan
// backlog penuh lagi) — nama & assertion test disesuaikan dengan perilaku baru.
test('beban: task due hari ini + overdue MASUK PENUH ke hari ini, task masa depan DISEBAR (F-52/F-118)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDashboardProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = Carbon::create(2026, 7, 20, 9, 0, 0); // Senin
    seedDashboardWorkSchedule($admin, $anchor, 480);
    $this->travelTo($anchor);

    createDashboardTask($project, $todo, $admin, $member, $anchor->copy()->endOfDay(), 120); // due hari ini -> hari_kerja=1, penuh 120
    createDashboardTask($project, $todo, $admin, $member, $anchor->copy()->subDays(2), 60); // overdue -> hari_kerja dipaksa 1, penuh 60
    // masa depan (Senin +1 minggu = Senin berikutnya): hari kerja Sen 20 s/d Sen 27
    // inklusif (Sabtu/Minggu dilewati F-43) = 6 hari kerja -> 200/6 = 33.33 ke hari
    // ini, sisanya 166.67 ke backlog (F-118 — TIDAK lagi 100% backlog seperti dulu).
    createDashboardTask($project, $todo, $admin, $member, $anchor->copy()->addWeek(), 200);

    $response = $this->actingAs($admin)->get(route('dashboard.summary', ['date' => $anchor->toDateString()]));

    $response->assertOk();
    $row = dashboardUserRow($response, $member->id);
    expect($row['beban'])->toBe(213) // 120 + 60 + round(200/6)
        ->and($row['backlog'])->toBe(167); // round(200 - 200/6)
});

test('task is_completed tidak masuk beban maupun backlog (F-19/F-44)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDashboardProject($admin, [$member->id]);
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();

    $anchor = Carbon::create(2026, 7, 20, 9, 0, 0);
    seedDashboardWorkSchedule($admin, $anchor, 480);
    $this->travelTo($anchor);

    // Selesai, due hari ini DAN masa depan -- keduanya tidak boleh terhitung
    // sama sekali (F-44: dicek lewat is_completed, bukan nama status).
    createDashboardTask($project, $done, $admin, $member, $anchor->copy()->endOfDay(), 999);
    createDashboardTask($project, $done, $admin, $member, $anchor->copy()->addWeek(), 999);

    $response = $this->actingAs($admin)->get(route('dashboard.summary', ['date' => $anchor->toDateString()]));

    $response->assertOk();
    $row = dashboardUserRow($response, $member->id);
    expect($row['beban'])->toBe(0)
        ->and($row['backlog'])->toBe(0);
});

test('idle_plan = kapasitas - beban', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createDashboardProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = Carbon::create(2026, 7, 20, 9, 0, 0);
    seedDashboardWorkSchedule($admin, $anchor, 480);
    $this->travelTo($anchor);

    createDashboardTask($project, $todo, $admin, $member, $anchor->copy()->endOfDay(), 180);

    $response = $this->actingAs($admin)->get(route('dashboard.summary', ['date' => $anchor->toDateString()]));

    $row = dashboardUserRow($response, $member->id);
    expect($row['idle_plan'])->toBe(480 - 180);
});

<?php

/**
 * ==========================================================
 * MODUL       : LeaderboardTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : v1.2/v1.5 Fase C (F-134) — buktikan LeaderboardController::index()/
 *               LeaderboardService::forPeriod() sesuai kontrak: Point HANYA dari
 *               task DISETUJUI (F-39 beku, C2), on-time% pakai original_due_date
 *               BUKAN due_date tergeser (F-47, C3), on-time% pakai submitted_at
 *               BUKAN approved_at supaya tidak tersandera kapan admin approve
 *               (2026-08-07, C3b), kolom konteks terpisah dari Point (F-62, C4),
 *               gating leaderboard.view TERMASUK admin biasa (F-134, C1), dan
 *               N+1 konstan (F-85, C5).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : LeaderboardController, LeaderboardService, RolePermissionSeeder
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Salah di sini = leaderboard bocor ke role yang tidak seharusnya
 *               lihat data produktivitas tim (F-134 management-only), atau Point
 *               ikut task yang belum final (F-39 — data KPI tidak boleh goyang).
 * ==========================================================
 */

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function createLbProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Leaderboard Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

// SUMBER: langsung set kolom RAW (points/quality_rating/rejection_count/approved_at)
// alih-alih menembak TaskTransitionService::approve() -- tes ini fokus ke LAPISAN
// AGREGASI (LeaderboardService membaca kolom apa adanya), bukan alur transisi
// status itu sendiri (sudah dites TaskTransitionTest.php). Pola sama createCcTask()
// di DashboardCommandCenterTest.php.
function createApprovedTask(Project $project, User $admin, array $assigneeIds, array $overrides = []): Task
{
    $completed = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();

    $task = Task::create(array_merge([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $completed->id,
        'title' => 'Lb task '.uniqid(),
        'task_type' => 'tentative',
        'points' => 10,
        'estimated_minutes' => 60,
        'due_date' => Carbon::now(),
        'quality_rating' => 5,
        'rejection_count' => 0,
        'approved_at' => Carbon::now(),
        'created_by' => $admin->id,
    ], $overrides));

    $task->assignees()->sync($assigneeIds);

    return $task;
}

// SUMBER: fetch Role via role_id (kolom, bukan relasi) -- Model::preventLazyLoading()
// aktif di non-produksi (F-85, AppServiceProvider), $user->role langsung dari objek
// factory yang belum di-load bakal melempar LazyLoadingViolationException.
function grantLeaderboardView(User $user): void
{
    $permission = Permission::where('permission_name', 'leaderboard.view')->firstOrFail();
    Role::whereKey($user->role_id)->firstOrFail()->permissions()->syncWithoutDetaching([$permission->id]);
}

// =============================================================================
// C1 -- gating leaderboard.view, TERMASUK admin biasa (F-134)
// =============================================================================

test('guest redirected, member forbidden, admin BIASA (tanpa leaderboard.view) juga forbidden (C1/F-134)', function () {
    $this->get(route('leaderboard.index'))->assertRedirect('/login');

    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    // F-134: admin TIDAK otomatis dapat leaderboard.view -- beda dari permission lain.
    $this->actingAs($admin)->get(route('leaderboard.index'))->assertForbidden();
    $this->actingAs($member)->get(route('leaderboard.index'))->assertForbidden();
});

test('user yang DIBERI leaderboard.view lewat UI RBAC (role) bisa akses (C1/F-135)', function () {
    $admin = User::factory()->admin()->create();
    grantLeaderboardView($admin);

    $this->actingAs($admin)->get(route('leaderboard.index'))->assertOk();
});

// =============================================================================
// C2 -- Point = Σ points task DISETUJUI; task belum-selesai TIDAK masuk (F-39)
// =============================================================================

test('point cuma dari task DISETUJUI dalam periode; task belum-selesai TIDAK ikut dihitung (C2/F-39)', function () {
    $admin = User::factory()->admin()->create();
    grantLeaderboardView($admin);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createLbProject($admin, [$member->id]);
    $anchor = Carbon::create(2026, 8, 10, 12, 0, 0);
    $this->travelTo($anchor);

    createApprovedTask($project, $admin, [$member->id], ['points' => 10, 'approved_at' => $anchor]);

    // Task belum disetujui (status TODO, bukan is_completed) dengan points BESAR --
    // kalau service ikut menghitungnya, Point akan meledak ke 1009, bukan 10.
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $openTask = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Belum selesai',
        'task_type' => 'tentative',
        'points' => 999,
        'estimated_minutes' => 60,
        'due_date' => $anchor->copy()->addDays(3),
        'created_by' => $admin->id,
    ]);
    $openTask->assignees()->sync([$member->id]);

    $response = $this->actingAs($admin)->get(route('leaderboard.index', ['from' => '2026-08-01', 'to' => '2026-08-31']));

    $response->assertOk();
    $rows = collect($response->viewData('page')['props']['rows']);
    expect($rows->firstWhere('id', $member->id)['point'])->toBe(10);
});

// =============================================================================
// C3 -- on-time% pakai original_due_date, BUKAN due_date yang sudah digeser (F-47)
// =============================================================================

test('task yang diperpanjang tidak palsu tampak on-time -- pakai original_due_date (C3/F-47)', function () {
    $admin = User::factory()->admin()->create();
    grantLeaderboardView($admin);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createLbProject($admin, [$member->id]);
    $anchor = Carbon::create(2026, 8, 10, 12, 0, 0);
    $this->travelTo($anchor);

    // Tenggat ASLI sudah lewat (anchor itu sendiri), tapi due_date SEKARANG sudah
    // digeser maju (extension disetujui) ke +10 hari. Task baru disetujui di +5
    // hari -- SEBELUM due_date baru (kelihatan "on-time" kalau salah pakai
    // due_date), tapi SESUDAH original_due_date (yang benar: TERLAMBAT).
    createApprovedTask($project, $admin, [$member->id], [
        'due_date' => $anchor->copy()->addDays(10),
        'original_due_date' => $anchor,
        'approved_at' => $anchor->copy()->addDays(5),
    ]);

    $response = $this->actingAs($admin)->get(route('leaderboard.index', ['from' => '2026-08-01', 'to' => '2026-08-31']));

    $response->assertOk();
    $rows = collect($response->viewData('page')['props']['rows']);
    // GUARD F-47: kalau service salah pakai due_date, angka ini akan 100 (palsu on-time).
    expect($rows->firstWhere('id', $member->id)['on_time_percent'])->toBe(0.0);
});

test('task TANPA perpanjangan (original_due_date null) pakai due_date sebagai tenggat asli (C3/F-47)', function () {
    $admin = User::factory()->admin()->create();
    grantLeaderboardView($admin);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createLbProject($admin, [$member->id]);
    $anchor = Carbon::create(2026, 8, 10, 12, 0, 0);
    $this->travelTo($anchor);

    createApprovedTask($project, $admin, [$member->id], [
        'due_date' => $anchor->copy()->addDay(),
        'original_due_date' => null,
        'approved_at' => $anchor,
    ]);

    $response = $this->actingAs($admin)->get(route('leaderboard.index', ['from' => '2026-08-01', 'to' => '2026-08-31']));

    $response->assertOk();
    $rows = collect($response->viewData('page')['props']['rows']);
    expect($rows->firstWhere('id', $member->id)['on_time_percent'])->toBe(100.0);
});

// =============================================================================
// C3b -- on-time% pakai submitted_at (submit pertama), BUKAN approved_at (2026-08-07)
// =============================================================================

test('member submit tepat waktu tapi admin telat approve TETAP tercatat on-time (C3b)', function () {
    // BUSINESS RULE (2026-08-07, keputusan Boss): approved_at tergantung kapan
    // ADMIN bertindak, bukan kapan MEMBER submit. Task ini submit SEBELUM deadline
    // tapi admin baru approve JAUH SETELAH deadline lewat -- kalau service masih
    // pakai approved_at, ini akan salah tercatat TELAT (0%) padahal member on-time.
    $admin = User::factory()->admin()->create();
    grantLeaderboardView($admin);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createLbProject($admin, [$member->id]);
    $anchor = Carbon::create(2026, 8, 10, 12, 0, 0);
    $this->travelTo($anchor);

    createApprovedTask($project, $admin, [$member->id], [
        'due_date' => $anchor,
        'submitted_at' => $anchor->copy()->subHour(), // submit 1 jam SEBELUM deadline
        'approved_at' => $anchor->copy()->addDays(5), // admin baru approve 5 hari kemudian
    ]);

    $response = $this->actingAs($admin)->get(route('leaderboard.index', ['from' => '2026-08-01', 'to' => '2026-08-31']));

    $response->assertOk();
    $rows = collect($response->viewData('page')['props']['rows']);
    expect($rows->firstWhere('id', $member->id)['on_time_percent'])->toBe(100.0);
});

test('task lama tanpa submitted_at (pra-migrasi) fallback ke approved_at (C3b)', function () {
    $admin = User::factory()->admin()->create();
    grantLeaderboardView($admin);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createLbProject($admin, [$member->id]);
    $anchor = Carbon::create(2026, 8, 10, 12, 0, 0);
    $this->travelTo($anchor);

    createApprovedTask($project, $admin, [$member->id], [
        'due_date' => $anchor,
        'submitted_at' => null,
        'approved_at' => $anchor->copy()->addDay(), // lewat deadline -> fallback harus TELAT
    ]);

    $response = $this->actingAs($admin)->get(route('leaderboard.index', ['from' => '2026-08-01', 'to' => '2026-08-31']));

    $response->assertOk();
    $rows = collect($response->viewData('page')['props']['rows']);
    expect($rows->firstWhere('id', $member->id)['on_time_percent'])->toBe(0.0);
});

// =============================================================================
// C4 -- kolom konteks (Rating/Revisi/Ditolak) benar & TERPISAH dari Point (F-62)
// =============================================================================

test('kolom konteks dihitung benar dan TIDAK dibaur ke Point (C4/F-62)', function () {
    $admin = User::factory()->admin()->create();
    grantLeaderboardView($admin);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createLbProject($admin, [$member->id]);
    $anchor = Carbon::create(2026, 8, 10, 12, 0, 0);
    $this->travelTo($anchor);

    createApprovedTask($project, $admin, [$member->id], ['points' => 10, 'quality_rating' => 3, 'rejection_count' => 2, 'approved_at' => $anchor]);
    createApprovedTask($project, $admin, [$member->id], ['points' => 20, 'quality_rating' => 5, 'rejection_count' => 0, 'approved_at' => $anchor]);

    $response = $this->actingAs($admin)->get(route('leaderboard.index', ['from' => '2026-08-01', 'to' => '2026-08-31']));

    $response->assertOk();
    $row = collect($response->viewData('page')['props']['rows'])->firstWhere('id', $member->id);

    // Point = 10+20=30, TIDAK terpengaruh rating rendah/revisi tinggi task pertama.
    expect($row['point'])->toBe(30)
        ->and($row['rating'])->toBe(4.0) // rata-rata (3+5)/2
        ->and($row['revisi'])->toBe(2) // Σ rejection_count
        ->and($row['ditolak'])->toBe(1); // COUNT task dgn rejection_count>0 (cuma 1 dari 2 task)
});

test('user tanpa task disetujui di periode tetap muncul dengan point 0 & konteks null (Bottom-3, §7.2)', function () {
    $admin = User::factory()->admin()->create();
    grantLeaderboardView($admin);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    createLbProject($admin, [$member->id]);

    $response = $this->actingAs($admin)->get(route('leaderboard.index', ['from' => '2026-08-01', 'to' => '2026-08-31']));

    $response->assertOk();
    $row = collect($response->viewData('page')['props']['rows'])->firstWhere('id', $member->id);
    expect($row['point'])->toBe(0)
        ->and($row['rating'])->toBeNull()
        ->and($row['on_time_percent'])->toBeNull();
});

// =============================================================================
// C5 -- N+1 konstan (F-85)
// =============================================================================

test('jumlah query leaderboard TETAP KONSTAN walau user/task bertambah banyak (C5/F-85)', function () {
    $admin = User::factory()->admin()->create();
    grantLeaderboardView($admin);
    $members = User::factory()->count(3)->create(['organization_id' => $admin->organization_id]);
    $project = createLbProject($admin, $members->pluck('id')->all());
    $anchor = Carbon::create(2026, 8, 10, 12, 0, 0);
    $this->travelTo($anchor);

    foreach ($members as $member) {
        createApprovedTask($project, $admin, [$member->id], ['approved_at' => $anchor]);
    }

    // Pemanasan (pola sama DashboardCommandCenterTest F-85).
    $this->actingAs($admin)->get(route('leaderboard.index'));

    DB::enableQueryLog();
    $this->actingAs($admin)->get(route('leaderboard.index'))->assertOk();
    $smallCount = count(DB::getQueryLog());
    DB::flushQueryLog();
    DB::disableQueryLog();

    $moreMembers = User::factory()->count(8)->create(['organization_id' => $admin->organization_id]);
    $project->members()->attach($moreMembers->pluck('id'));
    foreach ($moreMembers as $member) {
        createApprovedTask($project, $admin, [$member->id], ['approved_at' => $anchor]);
    }

    DB::enableQueryLog();
    $this->actingAs($admin)->get(route('leaderboard.index'))->assertOk();
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeCount)->toBe($smallCount);
});

// =============================================================================
// D1 (v1.4 KPI-2) -- kpi_total = Σ kpi_score task disetujui; Point=Σpts TETAP
// tidak terpengaruh (F-168, kolom terpisah)
// =============================================================================

test('kpi_total = Σ kpi_score task disetujui periode; Point=Σpts TETAP tak berubah (D1/F-168)', function () {
    $admin = User::factory()->admin()->create();
    grantLeaderboardView($admin);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createLbProject($admin, [$member->id]);
    $anchor = Carbon::create(2026, 8, 10, 12, 0, 0);
    $this->travelTo($anchor);

    // Poin & kpi_score SENGAJA angka berbeda -- kalau service keliru mencampur
    // keduanya (mis. kpi_total ikut Σpts), test ini ketahuan.
    createApprovedTask($project, $admin, [$member->id], ['points' => 10, 'kpi_score' => 5, 'approved_at' => $anchor]);
    createApprovedTask($project, $admin, [$member->id], ['points' => 20, 'kpi_score' => 3, 'approved_at' => $anchor]);

    $response = $this->actingAs($admin)->get(route('leaderboard.index', ['from' => '2026-08-01', 'to' => '2026-08-31']));

    $response->assertOk();
    $row = collect($response->viewData('page')['props']['rows'])->firstWhere('id', $member->id);

    expect($row['point'])->toBe(30) // Σpts TETAP -- F-168 guard utama.
        ->and($row['kpi_total'])->toBe(8); // Σ kpi_score = 5+3.
});

test('task disetujui dengan kpi_score null (approved saat kpi_enabled off) menyumbang 0 ke kpi_total, bukan error (D1/F-166)', function () {
    $admin = User::factory()->admin()->create();
    grantLeaderboardView($admin);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createLbProject($admin, [$member->id]);
    $anchor = Carbon::create(2026, 8, 10, 12, 0, 0);
    $this->travelTo($anchor);

    createApprovedTask($project, $admin, [$member->id], ['points' => 10, 'kpi_score' => null, 'approved_at' => $anchor]);

    $response = $this->actingAs($admin)->get(route('leaderboard.index', ['from' => '2026-08-01', 'to' => '2026-08-31']));

    $response->assertOk();
    $row = collect($response->viewData('page')['props']['rows'])->firstWhere('id', $member->id);
    expect($row['kpi_total'])->toBe(0);
});

// =============================================================================
// D2 (v1.4 KPI-2) -- kpi_enabled dikirim ke frontend, sumber gate kolom KPI
// =============================================================================

test('prop kpi_enabled dikirim ke halaman leaderboard sesuai config organisasi (D2/F-166)', function () {
    $admin = User::factory()->admin()->create();
    grantLeaderboardView($admin);

    $response = $this->actingAs($admin)->get(route('leaderboard.index'));
    $response->assertOk();
    expect($response->viewData('page')['props']['kpi_enabled'])->toBeTrue(); // default DB.

    $admin->organization->update(['kpi_enabled' => false]);

    $response = $this->actingAs($admin)->get(route('leaderboard.index'));
    $response->assertOk();
    expect($response->viewData('page')['props']['kpi_enabled'])->toBeFalse();
});

// =============================================================================
// F-4 -- nol pemetaan rupiah/gaji
// =============================================================================

test('F-4: nol field rupiah/gaji/reward di output leaderboard', function () {
    $admin = User::factory()->admin()->create();
    grantLeaderboardView($admin);
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createLbProject($admin, [$member->id]);
    $anchor = Carbon::create(2026, 8, 10, 12, 0, 0);
    $this->travelTo($anchor);

    createApprovedTask($project, $admin, [$member->id], ['approved_at' => $anchor]);

    $response = $this->actingAs($admin)->get(route('leaderboard.index', ['from' => '2026-08-01', 'to' => '2026-08-31']));

    $response->assertOk();
    $flat = json_encode($response->viewData('page')['props']);
    foreach (['rupiah', 'salary', 'gaji', 'reward'] as $forbidden) {
        expect(str_contains(strtolower($flat), $forbidden))->toBeFalse("field terlarang '{$forbidden}' bocor ke leaderboard (F-4)");
    }
});

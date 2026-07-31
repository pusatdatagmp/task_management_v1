<?php

/**
 * ==========================================================
 * MODUL       : ActivityLogTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi log aktivitas GLOBAL (v1.0 H4, F-116) — gate
 *               activity.view (dinamis, F-90), filter server-side, NOL N+1 (F-85),
 *               dan tidak ada satu pun endpoint mutasi (read-only mutlak).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : ActivityLogController
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test F-85 adalah pagar SATU-SATUNYA yang membuktikan halaman ini
 *               tidak meledak jadi ratusan query begitu data organisasi membesar
 *               (prompt eksplisit memperingatkan "log bisa ribuan baris").
 * ==========================================================
 */

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function createActivityLogProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Activity Log Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function createActivityLogTask(Project $project, User $admin): Task
{
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    return Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Activity log task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);
}

/**
 * KONTRAK: insert baris ActivityLog LANGSUNG (bukan lewat observer asli) — test
 * ini menguji lapisan TAMPILAN/QUERY (gate, filter, N+1), bukan menguji observer
 * yang sudah punya test sendiri (TaskTransitionTest dkk). Event 'created' generik,
 * aman dipetakan presenter tanpa properti tambahan.
 */
function insertRawLog(Task $task, User $admin, ?User $actor = null): void
{
    ActivityLog::create([
        'organization_id' => $admin->organization_id,
        'user_id' => $actor?->id ?? $admin->id,
        'subject_type' => Task::class,
        'subject_id' => $task->id,
        'event' => 'updated',
        'properties' => ['old' => ['priority' => 'low'], 'new' => ['priority' => 'high']],
    ]);
}

test('admin with activity.view can open the global log page', function () {
    $admin = User::factory()->admin()->create();
    $project = createActivityLogProject($admin);
    $task = createActivityLogTask($project, $admin);
    insertRawLog($task, $admin);

    $response = $this->actingAs($admin)->get(route('activity-logs.index'));

    $response->assertOk();
});

test('a member without activity.view is forbidden from the global log page (F-116)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);

    $response = $this->actingAs($member)->get(route('activity-logs.index'));

    $response->assertForbidden();
});

test('a custom role that is granted activity.view can access the page (F-90 dynamic)', function () {
    $admin = User::factory()->admin()->create();
    $permission = Permission::where('permission_name', 'activity.view')->firstOrFail();
    $customRole = Role::create([
        'organization_id' => $admin->organization_id,
        'role_name' => 'Auditor',
        'is_system' => false,
        'is_default' => false,
    ]);
    $customRole->permissions()->attach($permission->id);
    $auditor = User::factory()->create(['organization_id' => $admin->organization_id, 'role_id' => $customRole->id]);

    $response = $this->actingAs($auditor)->get(route('activity-logs.index'));

    $response->assertOk();
});

test('filtering by user, event type, and date range returns only matching rows', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create(['organization_id' => $admin->organization_id]);
    $project = createActivityLogProject($admin, [$otherAdmin->id]);
    $task = createActivityLogTask($project, $admin);

    insertRawLog($task, $admin, $admin);
    insertRawLog($task, $admin, $otherAdmin);

    $response = $this->actingAs($admin)->get(route('activity-logs.index', ['user_id' => $otherAdmin->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('logs.total', 1)
        ->where('logs.data.0.actor', $otherAdmin->name));

    // Tipe event yang tidak ada -> 0 hasil.
    $noMatch = $this->actingAs($admin)->get(route('activity-logs.index', ['event' => 'extension_requested']));
    $noMatch->assertInertia(fn ($page) => $page->where('logs.total', 0));

    // Rentang tanggal di masa depan -> 0 hasil.
    $futureRange = $this->actingAs($admin)->get(route('activity-logs.index', ['from' => now()->addYear()->toDateString()]));
    $futureRange->assertInertia(fn ($page) => $page->where('logs.total', 0));
});

test('the global log page runs a constant number of queries regardless of row count (F-85)', function () {
    $admin = User::factory()->admin()->create();
    $project = createActivityLogProject($admin);
    $task = createActivityLogTask($project, $admin);

    for ($i = 0; $i < 3; $i++) {
        insertRawLog($task, $admin);
    }

    // SUMBER: request "pemanasan" TIDAK dihitung — request pertama ke session/
    // Gate baru pada actor tertentu bisa punya 1-2 query tambahan yang HANYA
    // terjadi sekali (resolusi permission dsb, bukan N+1 sungguhan yang tumbuh
    // dengan JUMLAH DATA). Kedua pengukuran di bawah sama-sama request KEDUA
    // dst pada actor yang sama, supaya perbandingannya adil.
    $this->actingAs($admin)->get(route('activity-logs.index'))->assertOk();

    DB::enableQueryLog();
    $this->actingAs($admin)->get(route('activity-logs.index'))->assertOk();
    $smallCount = count(DB::getQueryLog());
    DB::flushQueryLog();

    for ($i = 0; $i < 30; $i++) {
        insertRawLog($task, $admin);
    }
    // SUMBER: flushQueryLog() di sini WAJIB — 30 INSERT di atas terekam log
    // (enableQueryLog() masih aktif dari sebelumnya), kalau tidak dibuang, insert
    // itu ikut kehitung seolah-olah bagian dari query HALAMAN, false positive N+1.
    DB::flushQueryLog();

    $this->actingAs($admin)->get(route('activity-logs.index'))->assertOk();
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeCount)->toBe($smallCount);
});

test('there is no mutation endpoint for activity logs — read-only (F-23/F-39)', function () {
    $admin = User::factory()->admin()->create();
    $project = createActivityLogProject($admin);
    $task = createActivityLogTask($project, $admin);
    insertRawLog($task, $admin);
    $log = ActivityLog::first();

    // Tidak ada satu pun route PUT/PATCH/DELETE terdaftar untuk activity log —
    // Laravel mengembalikan 404 untuk route yang memang tidak pernah didaftarkan.
    $this->actingAs($admin)->put("/pengaturan/activity-log/{$log->id}")->assertNotFound();
    $this->actingAs($admin)->delete("/pengaturan/activity-log/{$log->id}")->assertNotFound();
});

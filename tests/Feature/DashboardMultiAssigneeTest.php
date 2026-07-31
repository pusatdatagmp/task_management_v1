<?php

/**
 * ==========================================================
 * MODUL       : DashboardMultiAssigneeTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : Gerbang F-96 (F-63 diputuskan) — INTI hari ini. BEBAN/BACKLOG
 *               dibagi rata jumlah assignee per-task (pembagian PER-TASK dulu
 *               baru dijumlah, bukan SUM/SUM — 02-DATA-MODEL §5/B2). REALISASI
 *               tetap per-user dari task_time_segments.user_id, TIDAK dibagi
 *               (beda semangat dari beban). POIN (F-96b, utuh tiap assignee)
 *               SENGAJA TIDAK diuji di sini — Boss menahan agregasi poin mentah
 *               untuk v0.8 ini (LANGKAH 0, klarifikasi #3), tasks.points tetap
 *               utuh sebagai kolom RAW, cuma belum diagregasi DashboardService.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : DashboardController, DashboardService
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Kalau pembagian di sini salah (mis. SUM(estimasi)/SUM(assignee)),
 *               dashboard kapasitas tim BOHONG — admin bisa overload orang tanpa
 *               sadar karena beban tampak lebih kecil dari yang sebenarnya.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Testing\TestResponse;

function createMultiAssigneeProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Multi Assignee Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function seedMultiAssigneeSchedule(User $admin, Carbon $anchor): void
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

function createMultiAssigneeTask(Project $project, TaskStatus $status, User $admin, array $assigneeIds, int $estimatedMinutes, Carbon $dueDate): Task
{
    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'Multi assignee task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => $estimatedMinutes,
        'due_date' => $dueDate,
        'created_by' => $admin->id,
    ]);

    $task->assignees()->sync($assigneeIds);

    return $task;
}

function multiAssigneeUserRow(TestResponse $response, int $userId): array
{
    return collect($response->json('users'))->firstWhere('id', $userId);
}

test('beban: task 240 menit 2 assignee -> 120 tiap orang (F-96a)', function () {
    $admin = User::factory()->admin()->create();
    $memberA = User::factory()->create(['organization_id' => $admin->organization_id]);
    $memberB = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createMultiAssigneeProject($admin, [$memberA->id, $memberB->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = Carbon::create(2026, 7, 20, 9, 0, 0);
    seedMultiAssigneeSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createMultiAssigneeTask($project, $todo, $admin, [$memberA->id, $memberB->id], 240, $anchor->copy()->endOfDay());

    $response = $this->actingAs($admin)->get(route('dashboard.summary', ['date' => $anchor->toDateString()]));

    expect(multiAssigneeUserRow($response, $memberA->id)['beban'])->toBe(120)
        ->and(multiAssigneeUserRow($response, $memberB->id)['beban'])->toBe(120);
});

test('beban: task 240 menit 1 assignee -> 240 penuh, tidak dibagi', function () {
    $admin = User::factory()->admin()->create();
    $memberA = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createMultiAssigneeProject($admin, [$memberA->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = Carbon::create(2026, 7, 20, 9, 0, 0);
    seedMultiAssigneeSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createMultiAssigneeTask($project, $todo, $admin, [$memberA->id], 240, $anchor->copy()->endOfDay());

    $response = $this->actingAs($admin)->get(route('dashboard.summary', ['date' => $anchor->toDateString()]));

    expect(multiAssigneeUserRow($response, $memberA->id)['beban'])->toBe(240);
});

test('beban: task 300 menit 3 assignee -> 100 tiap orang (F-96a)', function () {
    $admin = User::factory()->admin()->create();
    $memberA = User::factory()->create(['organization_id' => $admin->organization_id]);
    $memberB = User::factory()->create(['organization_id' => $admin->organization_id]);
    $memberC = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createMultiAssigneeProject($admin, [$memberA->id, $memberB->id, $memberC->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $anchor = Carbon::create(2026, 7, 20, 9, 0, 0);
    seedMultiAssigneeSchedule($admin, $anchor);
    $this->travelTo($anchor);

    createMultiAssigneeTask($project, $todo, $admin, [$memberA->id, $memberB->id, $memberC->id], 300, $anchor->copy()->endOfDay());

    $response = $this->actingAs($admin)->get(route('dashboard.summary', ['date' => $anchor->toDateString()]));

    expect(multiAssigneeUserRow($response, $memberA->id)['beban'])->toBe(100)
        ->and(multiAssigneeUserRow($response, $memberB->id)['beban'])->toBe(100)
        ->and(multiAssigneeUserRow($response, $memberC->id)['beban'])->toBe(100);
});

/**
 * F-96: realisasi TETAP per-user dari segmen masing-masing, TIDAK ikut dibagi
 * jumlah assignee walau task-nya sama (beda semangat dari beban — kapasitas
 * jujur vs siapa benar-benar kerja berapa lama).
 */
test('realisasi (aktif): dua assignee kerja di task sama, masing-masing dapat angka miliknya sendiri, tidak dibagi', function () {
    $admin = User::factory()->admin()->create();
    $memberA = User::factory()->create(['organization_id' => $admin->organization_id]);
    $memberB = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createMultiAssigneeProject($admin, [$memberA->id, $memberB->id]);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();

    $anchor = Carbon::create(2026, 7, 20, 9, 0, 0); // Senin 09:00
    seedMultiAssigneeSchedule($admin, $anchor);

    $task = createMultiAssigneeTask($project, $inProgress, $admin, [$memberA->id, $memberB->id], 300, $anchor->copy()->addWeek());

    // A mulai 09:00 (akan akumulasi 180 menit sampai 12:00), B mulai 11:00 (akan
    // akumulasi 60 menit sampai 12:00) -- DUA segmen terbuka BERSAMAAN di task
    // yang sama (pola identik LiveTaskCounterTest, F-48 tidak melarang ini).
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $memberA->id,
        'started_at' => $anchor->copy(),
        'ended_at' => null,
    ]);
    $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $memberB->id,
        'started_at' => $anchor->copy()->addMinutes(120),
        'ended_at' => null,
    ]);

    $this->travelTo($anchor->copy()->addMinutes(180)); // sekarang 12:00

    $response = $this->actingAs($admin)->get(route('dashboard.summary', ['date' => $anchor->toDateString()]));

    $response->assertOk();
    expect(multiAssigneeUserRow($response, $memberA->id)['aktif'])->toBe(180)
        ->and(multiAssigneeUserRow($response, $memberB->id)['aktif'])->toBe(60);
});

<?php

/**
 * ==========================================================
 * MODUL       : TaskWorkActionsTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : H7 (F-132/F-138) — verifikasi 4 aksi Mulai/Hold/Lanjut/Submit:
 *               segmen HANYA terbuka lewat aksi eksplisit (D1/D7), drag/reject
 *               TIDAK LAGI membuka segmen otomatis (D2/D3), gate F-127 di Submit
 *               (D4), cap jam kerja F-57 tetap berlaku (D5), konsistensi F-94
 *               (D6), assignee-only F-95 (D7b).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskTransitionService (start/hold/resume/submit), LiveTaskCounter,
 *               DashboardService
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : D1 adalah pagar F-38 paling langsung untuk H7 — kalau Σ segmen
 *               salah di sini, realisasi SELURUH task lewat 4 tombol baru salah,
 *               dan F-39 akan membekukannya permanen saat approve.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\DashboardService;
use App\Services\LiveTaskCounter;
use Illuminate\Support\Carbon;

function createWorkActionsProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Work Actions Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function seedWorkActionsSchedule(User $admin, Carbon $effectiveFrom, array $daysOfWeek = [1, 2, 3, 4, 5], string $start = '08:00', string $end = '17:00'): WorkSchedule
{
    return WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => $effectiveFrom->toDateString(),
        'days_of_week' => $daysOfWeek,
        'start_time' => $start,
        'end_time' => $end,
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);
}

function createWorkActionsTask(Project $project, TaskStatus $status, User $admin, array $assigneeIds = []): Task
{
    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $status->id,
        'title' => 'Work actions task '.uniqid(),
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);

    if ($assigneeIds !== []) {
        $task->assignees()->sync($assigneeIds);
    }

    return $task;
}

test('D1: Mulai->Hold->Lanjut->Submit — realisasi = Σ segmen (jeda tak dihitung), status jadi review', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createWorkActionsProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createWorkActionsTask($project, $todo, $admin, [$member->id]);

    $anchor = Carbon::create(2026, 7, 20, 8, 0, 0); // Senin, jendela 24 jam biar fokus Σ, bukan cap.
    seedWorkActionsSchedule($admin, $anchor->copy()->subDay(), range(1, 7), '00:00', '23:59');

    $this->travelTo($anchor->copy());
    $this->actingAs($member)->patch(route('tasks.start', [$project, $task]))->assertRedirect();

    $task->refresh();
    expect($task->taskStatus->is_work_state)->toBeTrue();
    $segment1 = $task->timeSegments()->whereNull('ended_at')->firstOrFail();
    expect($segment1->user_id)->toBe($member->id);

    // 20 menit kerja -> Hold.
    $this->travelTo($anchor->copy()->addMinutes(20));
    $this->actingAs($member)->patch(route('tasks.hold', [$project, $task]))->assertRedirect();
    expect($task->timeSegments()->whereNull('ended_at')->count())->toBe(0);

    // 10 menit JEDA (tidak dihitung) -> Lanjut di menit ke-30.
    $this->travelTo($anchor->copy()->addMinutes(30));
    $this->actingAs($member)->patch(route('tasks.resume', [$project, $task]))->assertRedirect();
    expect($task->timeSegments()->whereNull('ended_at')->count())->toBe(1);

    // 15 menit kerja lagi -> Submit di menit ke-45.
    $this->travelTo($anchor->copy()->addMinutes(45));
    $this->actingAs($member)->patch(route('tasks.submit', [$project, $task]))->assertRedirect();

    $task->refresh();
    expect($task->taskStatus->is_review)->toBeTrue();
    expect($task->timeSegments()->whereNull('ended_at')->count())->toBe(0);
    expect($task->timeSegments()->count())->toBe(2);

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), ['quality_rating' => 5])->assertRedirect();

    // Σ segmen: 20 menit (segmen 1) + 15 menit (segmen 2) = 35, JEDA 10 menit TIDAK ikut.
    expect($task->fresh()->actual_minutes)->toBe(35);
});

test('D2: drag/dropdown ke dikerjakan = status SAJA, NOL segmen (F-138c)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createWorkActionsProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = createWorkActionsTask($project, $todo, $admin, [$member->id]);

    // Endpoint tasks.status SAMA dipakai dropdown TaskStatusCell & drop kartu Board (F-111).
    $this->actingAs($member)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ])->assertSessionDoesntHaveErrors();

    $task->refresh();
    expect($task->taskStatus->is_work_state)->toBeTrue();
    expect($task->timeSegments()->count())->toBe(0);
    expect($task->computeWorkState())->toBe('dikerjakan-jeda'); // F-138b: turunan, nol segmen = jeda
});

test('D3: reject -> ENTRY (nol segmen), MULAI yang baru membuka segmen (revisi 2026-08-07)', function () {
    // F-78: perilaku SENGAJA diubah (keputusan Boss 2026-08-07) -- reject dulu
    // mundur ke is_work_state terdekat (assertion lama: is_work_state===true,
    // 'dikerjakan-jeda', assignee klik Lanjut). Sekarang mundur ke status ENTRY
    // (flag semua false) -- assignee klik MULAI lagi, bukan Lanjut.
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createWorkActionsProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    // Task di review TANPA segmen terbuka -- state alami hasil Submit (sudah ditutup).
    $task = createWorkActionsTask($project, $review, $admin, [$member->id]);

    $this->actingAs($admin)->patch(route('tasks.reject', [$project, $task]), [
        'reason' => 'Revisi diperlukan.',
    ])->assertRedirect();

    $task->refresh();
    expect($task->task_status_id)->toBe($todo->id);
    expect($task->timeSegments()->whereNull('ended_at')->count())->toBe(0); // ENTRY, bukan auto-buka
    expect($task->computeWorkState())->toBe('todo');

    // Assignee klik MULAI sendiri (bukan Lanjut) -> BARU segmen baru terbuka.
    $this->actingAs($member)->patch(route('tasks.start', [$project, $task]))->assertRedirect();
    $segment = $task->timeSegments()->whereNull('ended_at')->firstOrFail();
    expect($segment->user_id)->toBe($member->id);
});

test('D4: Submit dengan checklist belum tuntas DITOLAK — status & segmen TIDAK berubah (F-127)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createWorkActionsProject($admin, [$member->id]);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = createWorkActionsTask($project, $inProgress, $admin, [$member->id]);
    $task->checklistItems()->create(['organization_id' => $admin->organization_id, 'text' => 'Belum selesai', 'position' => 0, 'is_done' => false]);

    $openSegment = $task->timeSegments()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $member->id,
        'started_at' => now(),
    ]);

    $response = $this->actingAs($member)->patch(route('tasks.submit', [$project, $task]));

    $response->assertSessionHasErrors('task_status_id');
    $task->refresh();
    expect($task->taskStatus->is_work_state)->toBeTrue(); // status TIDAK berubah
    expect($task->timeSegments()->whereNull('ended_at')->count())->toBe(1); // segmen TIDAK ditutup
    expect($openSegment->fresh()->ended_at)->toBeNull();
});

test('D5: F-57 — segmen lewat jam kerja & weekend, hanya jam kerja dihitung (alur Mulai/Submit eksplisit)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createWorkActionsProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createWorkActionsTask($project, $todo, $admin, [$member->id]);

    seedWorkActionsSchedule($admin, Carbon::create(2026, 1, 1)); // Mon-Fri 08:00-17:00

    // Jumat 16:00 mulai -> Senin 09:00 submit. Raw elapsed ~65 jam, TAPI cap
    // hanya menghitung jam kerja: Jumat 16:00-17:00 (60m) + Senin 08:00-09:00 (60m).
    $this->travelTo(Carbon::create(2026, 8, 7, 16, 0, 0)); // Jumat
    $this->actingAs($member)->patch(route('tasks.start', [$project, $task]))->assertRedirect();

    $this->travelTo(Carbon::create(2026, 8, 10, 9, 0, 0)); // Senin
    $this->actingAs($member)->patch(route('tasks.submit', [$project, $task]))->assertRedirect();

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), ['quality_rating' => 4])->assertRedirect();

    expect($task->fresh()->actual_minutes)->toBe(120); // BUKAN ~3900 menit raw
});

test('D6: F-94 — realisasi live/dashboard/freeze IDENTIK lewat alur Mulai eksplisit (bukan dropdown)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createWorkActionsProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createWorkActionsTask($project, $todo, $admin, [$member->id]);

    $anchor = Carbon::create(2026, 7, 20, 8, 0, 0);
    seedWorkActionsSchedule($admin, $anchor->copy()->subDay(), range(1, 7), '00:00', '23:59');

    $this->travelTo($anchor->copy());
    $this->actingAs($member)->patch(route('tasks.start', [$project, $task]))->assertRedirect();

    $this->travelTo($anchor->copy()->addMinutes(50));

    $liveCounter = (new LiveTaskCounter)->forTask($task->fresh(), $member);
    expect($liveCounter['accumulated_minutes'])->toBe(50);

    $dashboardRows = (new DashboardService)->forUsers(collect([$member]), $anchor->copy()->addMinutes(50));
    expect($dashboardRows[$member->id]['aktif'])->toBe(50);

    $this->actingAs($member)->patch(route('tasks.submit', [$project, $task]))->assertRedirect();
    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), ['quality_rating' => 5])->assertRedirect();

    // KONSISTENSI F-94: live (50) = dashboard aktif (50) = freeze actual_minutes (50).
    expect($task->fresh()->actual_minutes)->toBe(50);
});

test('D7: Mulai buka segmen atas nama ASSIGNEE yang KLIK, bukan assignee lain di task yang sama (F-112)', function () {
    $admin = User::factory()->admin()->create();
    $memberA = User::factory()->create(['organization_id' => $admin->organization_id]);
    $memberB = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createWorkActionsProject($admin, [$memberA->id, $memberB->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createWorkActionsTask($project, $todo, $admin, [$memberA->id, $memberB->id]);

    $this->actingAs($memberA)->patch(route('tasks.start', [$project, $task]))->assertRedirect();

    $segment = $task->timeSegments()->whereNull('ended_at')->firstOrFail();
    expect($segment->user_id)->toBe($memberA->id)->not->toBe($memberB->id);
});

test('D7b: admin (task.manage) BUKAN assignee -> Mulai ditolak 403, NOL bypass (F-95)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createWorkActionsProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    // Admin SENGAJA bukan assignee -- hanya $member.
    $task = createWorkActionsTask($project, $todo, $admin, [$member->id]);

    $this->actingAs($admin)->patch(route('tasks.start', [$project, $task]))->assertForbidden();
    expect($task->fresh()->taskStatus->is_work_state)->toBeFalse();
});

test('Hold gagal kalau task tidak sedang dikerjakan', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createWorkActionsProject($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $task = createWorkActionsTask($project, $todo, $admin, [$member->id]);

    $this->actingAs($member)->patch(route('tasks.hold', [$project, $task]))->assertSessionHasErrors('task_status_id');
});

test('Lanjut gagal kalau sesi assignee masih berjalan (bukan jeda)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createWorkActionsProject($admin, [$member->id]);
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $task = createWorkActionsTask($project, $inProgress, $admin, [$member->id]);
    $task->timeSegments()->create(['organization_id' => $admin->organization_id, 'user_id' => $member->id, 'started_at' => now()]);

    $this->actingAs($member)->patch(route('tasks.resume', [$project, $task]))->assertSessionHasErrors('task_status_id');
});

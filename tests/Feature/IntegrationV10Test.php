<?php

/**
 * ==========================================================
 * MODUL       : IntegrationV10Test
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi v1.0 SEBAGAI SATU SISTEM (Hari Verifikasi) — Board View,
 *               drag-drop, komentar/mention, dan activity log UI sudah teruji
 *               TERISOLASI di hari kerjanya masing-masing (BoardViewTest, BoardDragTest,
 *               CommentTest, MentionNotifTest, ActivityLogTest), tapi belum pernah
 *               dibuktikan bekerja BERSAMA dalam satu alur nyata yang juga menyentuh
 *               v0.5 (status transition) dan v0.8 (counter/dashboard) (pola F-73,
 *               sama seperti IntegrationV08Test). Juga membuktikan ULANG F-94/F-109
 *               (satu sumber realisasi lintas Board/Detail/Dashboard, kali ini
 *               Board sebagai salah satu pembaca) dan F-113 (komentar tidak
 *               mencemari activity_log) di dalam alur lintas-pilar yang sama.
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : BoardController, TaskController, CommentController, ActivityLogController,
 *               TaskTransitionService, LiveTaskCounter, DashboardService, ActivityLogPresenter
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test konsistensi angka adalah pagar SATU-SATUNYA yang membuktikan
 *               Board (v1.0) tidak diam-diam mulai menghitung realisasi sendiri
 *               berbeda dari Task Detail dan Dashboard (v0.8) — kalau drift, angka
 *               yang dilihat admin di 3 layar berbeda untuk task yang sama.
 * ==========================================================
 */

use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\DashboardService;
use App\Services\LiveTaskCounter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

function createV10Project(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Integration V10 Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function seedV10Schedule(User $admin, Carbon $effectiveFrom): WorkSchedule
{
    return WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => $effectiveFrom->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);
}

function v10Pdf(string $name = 'output.pdf'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'attach');
    file_put_contents($path, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<< /Type /Catalog >>\nendobj\n");

    return new UploadedFile($path, $name, null, null, true);
}

test('a task travels create -> board+list -> drag -> comment/mention -> approve, staying consistent across every v0.5/v0.8/v1.0 surface it touches', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createV10Project($admin, [$member->id]);
    seedV10Schedule($admin, Carbon::create(2026, 1, 1));

    $monday0800 = Carbon::create(2026, 8, 3, 8, 0, 0);
    $this->travelTo($monday0800);

    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Integration V10 task',
        'task_type' => 'tentative',
        'estimated_minutes' => 120,
        'priority' => 'normal',
        'points' => 5,
        'due_date' => $monday0800->copy()->addDays(3),
        'created_by' => $admin->id,
    ]);
    $task->assignees()->sync([$member->id]);

    // === A1/A3: TASK BARU MUNCUL DI BOARD DAN LIST VIEW, KOLOM/STATUS KONSISTEN ===
    $this->actingAs($admin)->get(route('tasks.index', $project))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tasks.data.0.id', $task->id)
            ->where('tasks.data.0.task_status_id', $todo->id));

    $this->actingAs($admin)->get(route('tasks.board', $project))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('columns.0.cards.0.id', $task->id)
            ->has('columns.1.cards', 0));

    // === A1: DRAG (endpoint tasks.status, sama dipakai dropdown & drop kartu) ===
    // Pelaku = ASSIGNEE sendiri di sini supaya sekaligus jadi baseline untuk C3
    // (BoardDragTest sudah membuktikan kasus admin-menggeser-task-orang secara
    // terisolasi; alur ini fokus pada konsistensi lintas-fitur, bukan mengulang C3).
    // F-78 (H7/F-138c): DULU drag/dropdown ke IN_PROGRESS otomatis buka segmen
    // (assertion lama: F-112 segmen atas nama assignee). SEKARANG drag = STATUS
    // SAJA, NOL segmen — assignee klik Mulai sendiri (baris di bawah) untuk
    // benar-benar membuka sesi kerja, supaya assertion realisasi berikutnya
    // (45 menit, 3 permukaan) tetap punya segmen nyata untuk dihitung.
    $this->actingAs($member)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ])->assertSessionDoesntHaveErrors();

    $task->refresh();
    expect($task->task_status_id)->toBe($inProgress->id);
    expect($task->timeSegments()->count())->toBe(0); // F-138c: drag = status saja, nol segmen

    // Task SUDAH is_work_state (dipindah drag di atas) tapi JEDA (nol segmen) --
    // assignee klik Lanjut (BUKAN Mulai, itu khusus dari status todo) untuk
    // benar-benar membuka sesi kerja. F-112 tetap benar: atas nama assignee yang
    // klik, di sini juga pelaku, kasus paling umum.
    $this->actingAs($member)->patch(route('tasks.resume', [$project, $task]))->assertSessionDoesntHaveErrors();
    $openSegment = $task->timeSegments()->whereNull('ended_at')->firstOrFail();
    expect($openSegment->user_id)->toBe($member->id);

    // F-111: status berubah lewat SERVICE+OBSERVER yang sama -> F-51 activity_log
    // WAJIB mencatat status_changed, bukan silent update.
    expect(ActivityLog::where('subject_type', Task::class)
        ->where('subject_id', $task->id)
        ->where('event', 'status_changed')
        ->exists())->toBeTrue();

    // A3: List View DAN Board sama-sama ikut pindah -- satu sumber data (Task::task_status_id).
    $this->actingAs($admin)->get(route('tasks.index', $project))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tasks.data.0.task_status_id', $inProgress->id));

    $this->actingAs($admin)->get(route('tasks.board', $project))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('columns.0.cards', 0)
            ->where('columns.1.cards.0.id', $task->id));

    // === A2/F-94/F-109: REALISASI SATU SUMBER, DIBACA DARI 3 PERMUKAAN BEDA ===
    $fortyFiveMinutesLater = $monday0800->copy()->addMinutes(45);
    $this->travelTo($fortyFiveMinutesLater);

    $directCounter = (new LiveTaskCounter)->forTask($task->fresh(), $member);
    expect($directCounter['accumulated_minutes'])->toBe(45);

    // Permukaan 1: kartu Board.
    $this->actingAs($member)->get(route('tasks.board', $project))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('columns.1.cards.0.live_counter.accumulated_minutes', $directCounter['accumulated_minutes']));

    // Permukaan 2: halaman detail task.
    $this->actingAs($member)->get(route('tasks.show', [$project, $task]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('task.live_counter.accumulated_minutes', $directCounter['accumulated_minutes']));

    // Permukaan 3: dashboard (kolom 'aktif' = segmen sedang berjalan).
    $dashboardDuringWork = (new DashboardService)->forUsers(collect([$member]), $fortyFiveMinutesLater);
    expect($dashboardDuringWork[$member->id]['aktif'])->toBe($directCounter['accumulated_minutes']);

    // === A1: KOMENTAR + MENTION -- notif ADA, activity_log TIDAK (F-113/F-114) ===
    $this->actingAs($member)->post(route('comments.store', [$project, $task]), [
        'body' => "Sudah saya kerjakan separuh, cc @[{$admin->name}]({$admin->id}).",
    ])->assertSessionDoesntHaveErrors();

    $comment = Comment::where('task_id', $task->id)->firstOrFail();
    expect($comment->mentioned_user_ids)->toBe([$admin->id]);

    $mentionNotifs = $admin->notifications()->get()->filter(fn ($n) => ($n->data['type'] ?? null) === 'mentioned');
    expect($mentionNotifs)->toHaveCount(1);

    // F-113: TIDAK ADA event apa pun terkait komentar di activity_logs, dan
    // komentar itu sendiri TIDAK PERNAH jadi subject di sana.
    expect(ActivityLog::where('subject_type', Comment::class)->count())->toBe(0)
        ->and(ActivityLog::where('subject_type', Task::class)->where('subject_id', $task->id)->pluck('event'))
        ->not->toContain('comment_added', 'comment_updated', 'comment_deleted');

    // === A1: ATTACHMENT + REVIEW + APPROVE -- freeze, kunci, dashboard beban turun ===
    $this->actingAs($member)->post(route('attachments.store', [$project, $task]), [
        'content_type' => 'file',
        'file' => v10Pdf(),
    ])->assertRedirect()->assertSessionDoesntHaveErrors();
    $attachment = Attachment::where('task_id', $task->id)->firstOrFail();

    $this->actingAs($member)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $review->id,
    ])->assertSessionDoesntHaveErrors();

    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), [
        'quality_rating' => 4,
    ])->assertRedirect();

    $task->refresh();
    // F-39: angka beku HARUS PERSIS sama dengan yang barusan tampil live di 3
    // permukaan di atas -- bukti tidak ada rumus ke-4 khusus freeze.
    expect($task->actual_minutes)->toBe($directCounter['accumulated_minutes']);

    // F-104/F-107: attachment terkunci TOTAL pasca-approve, bahkan admin.
    $this->actingAs($member)->post(route('attachments.store', [$project, $task]), [
        'content_type' => 'file',
        'file' => v10Pdf('revisi.pdf'),
    ])->assertForbidden();
    $this->actingAs($admin)->delete(route('attachments.destroy', [$project, $task, $attachment]))
        ->assertForbidden();

    // Dashboard: task sudah is_completed -> beban 0, tidak ada lagi segmen terbuka -> aktif 0.
    $dashboardAfterApprove = (new DashboardService)->forUsers(collect([$member]), $fortyFiveMinutesLater);
    expect($dashboardAfterApprove[$member->id]['beban'])->toBe(0)
        ->and($dashboardAfterApprove[$member->id]['aktif'])->toBe(0);

    // === A1: SEMUA AKSI DI ATAS TEREKAM DI ACTIVITY LOG DENGAN LABEL MANUSIAWI (F-106) ===
    $taskDetail = $this->actingAs($admin)->get(route('tasks.show', [$project, $task]));
    $taskDetail->assertOk();

    $rawLogs = ActivityLog::where('subject_type', Task::class)->where('subject_id', $task->id)->get();
    $events = $rawLogs->pluck('event');
    expect($events)->toContain('status_changed')
        ->and($events)->toContain('approved');

    // F-106: pesan yang dikirim ke frontend HARUS kalimat Indonesia, bukan nama
    // event mentah -- string event snake_case TIDAK BOLEH muncul verbatim di pesan.
    $messages = collect($taskDetail->viewData('page')['props']['task']['activity_logs'])->pluck('message');
    expect($messages)->not->toBeEmpty();
    foreach ($messages as $message) {
        expect($message)->not->toContain('status_changed')
            ->and($message)->not->toContain('_changed')
            ->and($message)->toMatch('/^[A-Z]/'); // kalimat manusiawi diawali huruf besar (nama pelaku), bukan snake_case
    }

    // Global log (F-116) juga membaca dari sumber SAMA -- admin sudah punya
    // activity.view (permission default), harus bisa melihat baris yang sama.
    $this->actingAs($admin)->get(route('activity-logs.index'))->assertOk();
});

test('a member without activity.view is forbidden from the global log even after acting on the same task (F-116)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createV10Project($admin, [$member->id]);
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Task activity log gating',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => now()->addWeek(),
        'created_by' => $admin->id,
    ]);
    $task->assignees()->sync([$member->id]);

    $this->actingAs($member)->post(route('comments.store', [$project, $task]), [
        'body' => 'Komentar member biasa.',
    ])->assertSessionDoesntHaveErrors();

    // Member TETAP bisa lihat riwayat PER-TASK sendiri (F-95 membership, bukan
    // permission) tapi TIDAK BOLEH lihat log GLOBAL (F-116 permission-gated).
    $this->actingAs($member)->get(route('tasks.show', [$project, $task]))->assertOk();
    $this->actingAs($member)->get(route('activity-logs.index'))->assertForbidden();
});

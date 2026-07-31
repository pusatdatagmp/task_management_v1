<?php

/**
 * ==========================================================
 * MODUL       : IntegrationV08Test
 * KLASIFIKASI : UTIL
 * TUJUAN      : Verifikasi v0.8 SEBAGAI SATU SISTEM (Hari-7/H7) — tiap pilar
 *               (recurring, counter, dashboard, attachment, extension) sudah
 *               teruji TERISOLASI di hari kerjanya masing-masing, tapi belum
 *               pernah dibuktikan bekerja BERSAMA dalam satu alur nyata (F-73).
 *               Juga membuktikan F-94 (satu sumber realisasi: BusinessHoursCalculator)
 *               dan F-96 (beban dashboard dibagi, data Task sendiri TIDAK dibagi).
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : GenerateRecurringTasksCommand, DashboardService, LiveTaskCounter,
 *               TaskTransitionService, AttachmentController, DeadlineExtensionObserver
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : Test 1 adalah pagar SATU-SATUNYA yang membuktikan angka yang
 *               dibekukan F-39 SAMA PERSIS dengan angka yang sempat tampil live
 *               di counter SEBELUM freeze — kalau ini lolos diam-diam, quality_rating
 *               admin bisa dinilai dari angka yang berbeda dari yang assignee lihat.
 * ==========================================================
 */

use App\Models\Attachment;
use App\Models\DeadlineExtension;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\BusinessHoursCalculator;
use App\Services\DashboardService;
use App\Services\LiveTaskCounter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

function createIntegrationProject(User $admin, array $memberIds = []): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Integration Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);

    $project->members()->sync(array_unique([$admin->id, ...$memberIds]));
    TaskStatus::seedDefaults($project);

    return $project;
}

function seedIntegrationSchedule(User $admin, Carbon $effectiveFrom): WorkSchedule
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

function integrationPdf(string $name = 'output.pdf'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'attach');
    file_put_contents($path, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<< /Type /Catalog >>\nendobj\n");

    return new UploadedFile($path, $name, null, null, true);
}

test('recurring -> dashboard beban -> counter live -> attachment -> review -> approve freezes the SAME number the live counter showed, unlocks dashboard, locks attachment (F-38/F-94/F-96/F-104/F-107/F-52)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createIntegrationProject($admin, [$member->id]);
    seedIntegrationSchedule($admin, Carbon::create(2026, 1, 1));

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Integration daily template',
        'task_type' => 'daily',
        'estimated_minutes' => 120,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => [$member->id],
        'is_active' => true,
    ]);

    $monday0800 = Carbon::create(2026, 8, 3, 8, 0, 0); // Senin, tepat jam buka jendela kerja.
    $this->travelTo($monday0800);

    // === PILAR 1: RECURRING (F-46/F-100) ===
    $this->artisan('tasks:generate-recurring')->assertSuccessful();
    $task = Task::where('task_template_id', $template->id)->firstOrFail();
    expect($task->due_date->toDateString())->toBe('2026-08-03');

    // === PILAR 2: DASHBOARD BEBAN (F-52) — task due HARI INI, belum selesai. ===
    $dashboard = new DashboardService;
    $rowsBefore = $dashboard->forUsers(collect([$member]), $monday0800);
    expect($rowsBefore[$member->id]['beban'])->toBe(120);

    // === PILAR 3: COUNTER LIVE (F-38/F-94) — mulai kerja, majukan waktu 90 menit. ===
    $inProgress = TaskStatus::where('project_id', $project->id)->where('is_work_state', true)->firstOrFail();
    $this->actingAs($member)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $inProgress->id,
    ])->assertRedirect();

    $ninetyMinutesLater = $monday0800->copy()->addMinutes(90);
    $this->travelTo($ninetyMinutesLater);

    $liveCounter = (new LiveTaskCounter)->forTask($task->fresh(), $member);
    expect($liveCounter['accumulated_minutes'])->toBe(90);

    // KONSISTENSI F-94 #1: dashboard 'aktif' (segmen OPEN) HARUS sama dengan
    // angka yang barusan tampil di counter live — dua pemanggil BEDA
    // (LiveTaskCounter vs DashboardService), SATU sumber (BusinessHoursCalculator).
    $rowsDuringWork = $dashboard->forUsers(collect([$member]), $ninetyMinutesLater);
    expect($rowsDuringWork[$member->id]['aktif'])->toBe(90);

    // === PILAR 4: ATTACHMENT OUTPUT (F-49) — bebas selama task belum approved. ===
    $this->actingAs($member)->post(route('attachments.store', [$project, $task]), [
        'file' => integrationPdf(),
    ])->assertRedirect()->assertSessionDoesntHaveErrors();
    $attachment = Attachment::where('task_id', $task->id)->firstOrFail();

    // Submit ke REVIEW -> menutup segmen PERSIS di waktu simulasi sekarang (90 menit).
    $review = TaskStatus::where('project_id', $project->id)->where('is_review', true)->firstOrFail();
    $this->actingAs($member)->patch(route('tasks.status', [$project, $task]), [
        'task_status_id' => $review->id,
    ])->assertRedirect();

    // === APPROVE (F-28/F-39) — freeze actual_minutes. ===
    $this->actingAs($admin)->patch(route('tasks.approve', [$project, $task]), [
        'quality_rating' => 4,
    ])->assertRedirect();

    $task->refresh();

    // KONSISTENSI F-94 #2: angka yang DIBEKUKAN harus PERSIS sama dengan angka
    // yang sempat tampil live SEBELUM approve (90 menit) — bukti tidak ada 3
    // rumus realisasi yang berbeda (counter/dashboard/freeze).
    expect($task->actual_minutes)->toBe(90);

    // Cross-check independen langsung ke BusinessHoursCalculator (sumber tunggal F-94).
    $calculator = new BusinessHoursCalculator;
    $rawSegment = $task->timeSegments()->firstOrFail();
    $directCalculation = $calculator->overlapMinutes(
        $rawSegment->started_at,
        $rawSegment->ended_at,
        WorkSchedule::where('organization_id', $admin->organization_id)->get(),
        collect(),
    );
    expect($directCalculation)->toBe(90)->toBe($task->actual_minutes);

    // === PILAR 4 LAGI: ATTACHMENT TERKUNCI PASCA-APPROVE (F-104/F-107) ===
    $lockedUpload = $this->actingAs($member)->post(route('attachments.store', [$project, $task]), [
        'file' => integrationPdf('revisi.pdf'),
    ]);
    $lockedUpload->assertForbidden();

    $lockedDelete = $this->actingAs($admin)->delete(route('attachments.destroy', [$project, $task, $attachment]));
    $lockedDelete->assertForbidden();

    // === PILAR 2 LAGI: DASHBOARD SETELAH APPROVE ===
    // beban HARUS 0 -- task sudah is_completed, keluar dari hitungan beban (F-52
    // "task BELUM selesai"). aktif HARUS 0 -- segmen sudah ditutup, tidak ada
    // lagi yang "sedang berjalan". idle_real HARUS menyerap 90 menit yang barusan
    // dibekukan (closed hari yang sama).
    $rowsAfterApprove = $dashboard->forUsers(collect([$member]), $ninetyMinutesLater);
    expect($rowsAfterApprove[$member->id]['beban'])->toBe(0)
        ->and($rowsAfterApprove[$member->id]['aktif'])->toBe(0)
        ->and($rowsAfterApprove[$member->id]['idle_real'])->toBe(480 - 90);
});

test('a task generated for 2 assignees has its workload split in the dashboard but stays whole on the task itself (F-96)', function () {
    $admin = User::factory()->admin()->create();
    $memberA = User::factory()->create(['organization_id' => $admin->organization_id]);
    $memberB = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createIntegrationProject($admin, [$memberA->id, $memberB->id]);
    seedIntegrationSchedule($admin, Carbon::create(2026, 1, 1));

    $template = TaskTemplate::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'title' => 'Integration multi-assignee template',
        'task_type' => 'daily',
        'estimated_minutes' => 200,
        'points' => 5,
        'priority' => 'normal',
        'recurrence_config' => [],
        'default_assignees' => [$memberA->id, $memberB->id],
        'is_active' => true,
    ]);

    $this->travelTo(Carbon::create(2026, 8, 3, 8, 0, 0));
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $task = Task::where('task_template_id', $template->id)->firstOrFail();
    expect($task->assignees)->toHaveCount(2)
        // SUMBER: Task itu sendiri TIDAK PERNAH dibagi -- pembagian F-96 murni
        // agregasi di lapisan dashboard, bukan mutasi data.
        ->and($task->estimated_minutes)->toBe(200);

    $dashboard = new DashboardService;
    $rows = $dashboard->forUsers(collect([$memberA, $memberB]), Carbon::now());

    expect($rows[$memberA->id]['beban'])->toBe(100)
        ->and($rows[$memberB->id]['beban'])->toBe(100);
});

// F-78: PEMBARUAN (bukan tambalan) — sebelum v1.0.1, task yang di-extend ke
// tenggat masa depan pindah 100% dari beban ke backlog. F-59/F-118 (keputusan
// Boss) mengubah ini: tenggat baru DISEBAR ke hari kerja, jadi task tetap
// menyumbang SEBAGIAN ke beban hari ini juga (bukan 0 lagi).
test('extension approval SPREADS the task across business days to the NEW due_date, dashboard uses NEW due_date while original_due_date stays anchored (F-47/F-50/F-52/F-118)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createIntegrationProject($admin, [$member->id]);
    seedIntegrationSchedule($admin, Carbon::create(2026, 1, 1));
    $todo = TaskStatus::where('project_id', $project->id)->where('position', 0)->firstOrFail();

    $monday = Carbon::create(2026, 8, 3, 8, 0, 0);
    $this->travelTo($monday);

    $originalDue = $monday->copy()->setTime(17, 0);
    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $todo->id,
        'title' => 'Task due today, to be extended',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'due_date' => $originalDue,
        'created_by' => $admin->id,
    ]);
    $task->assignees()->sync([$member->id]);

    $dashboard = new DashboardService;
    $rowsBefore = $dashboard->forUsers(collect([$member]), $monday);
    expect($rowsBefore[$member->id]['beban'])->toBe(60)
        ->and($rowsBefore[$member->id]['backlog'])->toBe(0);

    $newDue = $monday->copy()->addDays(5);
    $this->actingAs($member)->post(route('extensions.store'), [
        'task_id' => $task->id,
        'requested_due_date' => $newDue->format('Y-m-d H:i:s'),
        'reason' => 'Butuh waktu tambahan, geser ke minggu depan.',
    ])->assertRedirect();

    $extension = DeadlineExtension::where('task_id', $task->id)->firstOrFail();
    $this->actingAs($admin)->patch(route('extensions.approve', $extension), [])->assertRedirect();

    $task->refresh();
    expect($task->original_due_date?->toDateTimeString())->toBe($originalDue->toDateTimeString())
        ->and($task->due_date->toDateTimeString())->toBe($newDue->toDateTimeString());

    // Dashboard HARUS pakai due_date BARU -- original_due_date TIDAK dipakai
    // untuk bucketing (F-47 tetap murni anchor evaluasi, bukan sumber beban).
    // newDue = Senin +5 hari = SABTU (bukan hari kerja) -> hari kerja terakhir
    // yang dihitung adalah Jumat (A3): Sen,Sel,Rab,Kam,Jum = 5 hari kerja.
    // estimasi 60 / 5 hari kerja = 12 ke beban hari ini, sisa 48 ke backlog
    // (F-118 -- BUKAN lagi 0/60 seperti rumus lama).
    $rowsAfter = $dashboard->forUsers(collect([$member]), $monday);
    expect($rowsAfter[$member->id]['beban'])->toBe(12)
        ->and($rowsAfter[$member->id]['backlog'])->toBe(48);
});

test('a task with realisasi > 3x estimasi is flagged as an anomaly in the dashboard WITHOUT any automatic penalty (F-53)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createIntegrationProject($admin, [$member->id]);
    $done = TaskStatus::where('project_id', $project->id)->where('is_completed', true)->firstOrFail();

    $now = Carbon::create(2026, 8, 3, 15, 0, 0);
    $this->travelTo($now);

    $task = Task::create([
        'organization_id' => $admin->organization_id,
        'project_id' => $project->id,
        'task_status_id' => $done->id,
        'title' => 'Task anomali',
        'task_type' => 'tentative',
        'estimated_minutes' => 60,
        'actual_minutes' => 300, // 5x estimasi
        'quality_rating' => 4,
        'due_date' => $now,
        'completed_at' => $now,
        'approved_at' => $now,
        'approved_by' => $admin->id,
        'created_by' => $admin->id,
    ]);
    $task->assignees()->sync([$member->id]);

    $dashboard = new DashboardService;
    $rows = $dashboard->forUsers(collect([$member]), $now);

    expect($rows[$member->id]['anomalies'])->toHaveCount(1)
        ->and($rows[$member->id]['anomalies'][0]['task_id'])->toBe($task->id);

    // TIDAK ADA HUKUMAN OTOMATIS: task_status_id/quality_rating tetap seperti
    // yang di-set admin, tidak ada kolom lain yang berubah gara-gara terdeteksi anomali.
    $task->refresh();
    expect($task->task_status_id)->toBe($done->id)
        ->and($task->quality_rating)->toBe(4);
});

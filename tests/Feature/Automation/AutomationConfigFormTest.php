<?php

/**
 * ==========================================================
 * MODUL       : AutomationConfigFormTest
 * KLASIFIKASI : UTIL
 * TUJUAN      : D3+D4 (prompt AE-2b) — form Tugas Berulang MENULIS kolom
 *               Automation Engine dengan benar (D3, tiap anchor type + guard +
 *               validasi tolak nilai ngawur) DAN engine MEMBACA config itu lalu
 *               patuh (D4, end-to-end "Boss atur -> engine jalan sesuai").
 * DIPANGGIL   : php artisan test (Pest)
 * MEMANGGIL   : TaskTemplateController::store()/update(), RunAutomationEngineCommand
 * DATA MASUK  : -
 * DATA KELUAR : Assertion pass/fail
 * RISIKO      : D4 adalah pagar PALING PENTING AE-2b — kalau form menyimpan
 *               kolom yang TIDAK dibaca engine (typo nama field, bentuk JSON
 *               beda), Boss akan mengira sudah mengatur jadwal padahal engine
 *               diam-diam mengabaikannya. Test ini lewat HTTP POST/PUT SUNGGUHAN
 *               (bukan TaskTemplate::create() langsung) supaya benar-benar
 *               menembus StoreTaskTemplateRequest -> normalizeAutomationConfig()
 *               -> kolom DB -> Pipeline, jalur yang SAMA dipakai Boss di browser.
 * ==========================================================
 */

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;

function createConfigFormProject(User $admin): Project
{
    $project = Project::create([
        'organization_id' => $admin->organization_id,
        'name' => 'Config Form Test Project '.uniqid(),
        'owner_id' => $admin->id,
    ]);
    $project->members()->sync([$admin->id]);
    TaskStatus::seedDefaults($project);

    return $project;
}

function seedConfigFormSchedule(User $admin): WorkSchedule
{
    return WorkSchedule::create([
        'organization_id' => $admin->organization_id,
        'effective_from' => Carbon::create(2026, 1, 1)->toDateString(),
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
        'daily_capacity_minutes' => 480,
        'created_by' => $admin->id,
    ]);
}

function baseTemplatePayload(): array
{
    return [
        'task_type' => 'daily',
        'estimated_minutes' => 30,
        'points' => 5,
        'recurrence_config' => [],
        'default_assignees' => [],
    ];
}

// ---------------------------------------------------------------------------
// D3: form menyimpan tiap anchor type + guard -> kolom terisi benar
// ---------------------------------------------------------------------------

test('D3a: simpan anchor A (time_based) -> interval_value/unit terisi, anchor_config kosong', function () {
    $admin = User::factory()->admin()->create();
    $project = createConfigFormProject($admin);

    $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        ...baseTemplatePayload(),
        'title' => 'Anchor A',
        'anchor_strategy' => 'time_based',
        'interval_value' => 5,
        'interval_unit' => 'day',
    ])->assertRedirect();

    $template = TaskTemplate::where('title', 'Anchor A')->firstOrFail();
    expect($template->anchor_strategy)->toBe('time_based');
    expect($template->interval_value)->toBe(5);
    expect($template->interval_unit)->toBe('day');
    // normalizeAutomationConfig(): anchor_config DIPAKSA null untuk anchor
    // selain calendar_anchored (TaskTemplateController) -- bukan [].
    expect($template->anchor_config)->toBeNull();
});

test('bug fix 2026-08-06 (Boss lapor form Template tidak bisa disimpan): anchor_day_type eksplisit null dengan time_based -> BERHASIL', function () {
    $admin = User::factory()->admin()->create();
    $project = createConfigFormProject($admin);

    // Payload ini SETELAH fix frontend (task-templates/create.tsx transform()) --
    // anchor_day_type di-null-kan kalau anchor_strategy BUKAN calendar_anchored,
    // BUKAN dibiarkan default 'week' dari form state. SEBELUM fix, form SELALU
    // mengirim anchor_day_type:'week' apa pun strateginya -- lihat test di bawah
    // yang membuktikan payload LAMA (bug) memang ditolak server.
    $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        ...baseTemplatePayload(),
        'title' => 'Bug fix anchor_day_type null',
        'anchor_strategy' => 'time_based',
        'interval_value' => 1,
        'interval_unit' => 'day',
        'anchor_day_type' => null,
    ])->assertRedirect()->assertSessionDoesntHaveErrors();

    expect(TaskTemplate::where('title', 'Bug fix anchor_day_type null')->exists())->toBeTrue();
});

test('bug fix 2026-08-06 (dokumentasi akar masalah): anchor_day_type=week BOCOR ke time_based -> DITOLAK (backend TIDAK diubah, fix di frontend)', function () {
    $admin = User::factory()->admin()->create();
    $project = createConfigFormProject($admin);

    // Ini PERSIS payload form SEBELUM fix -- anchor_day_type default form
    // ('week') ikut terkirim walau anchor_strategy time_based. Server WAJIB
    // tetap menolak ini (rule anchor_config.day_of_week required_if:anchor_day_type,week
    // TIDAK disentuh) -- test ini mendokumentasikan KENAPA fix ada di frontend
    // (transform()), bukan melonggarkan validasi backend.
    $response = $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        ...baseTemplatePayload(),
        'title' => 'Payload lama (bug)',
        'anchor_strategy' => 'time_based',
        'interval_value' => 1,
        'interval_unit' => 'day',
        'anchor_day_type' => 'week',
        'anchor_config' => [],
    ]);

    $response->assertSessionHasErrors('anchor_config.day_of_week');
    expect(TaskTemplate::where('title', 'Payload lama (bug)')->exists())->toBeFalse();
});

test('D3b: simpan anchor B (completion_based) -> interval terisi sama seperti A', function () {
    $admin = User::factory()->admin()->create();
    $project = createConfigFormProject($admin);

    $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        ...baseTemplatePayload(),
        'title' => 'Anchor B',
        'anchor_strategy' => 'completion_based',
        'interval_value' => 2,
        'interval_unit' => 'week',
    ])->assertRedirect();

    $template = TaskTemplate::where('title', 'Anchor B')->firstOrFail();
    expect($template->anchor_strategy)->toBe('completion_based');
    expect($template->interval_value)->toBe(2);
    expect($template->interval_unit)->toBe('week');
});

test('D3c: simpan anchor C hari-dalam-minggu -> anchor_config={day_of_week}, interval NULL', function () {
    $admin = User::factory()->admin()->create();
    $project = createConfigFormProject($admin);

    $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        ...baseTemplatePayload(),
        'title' => 'Anchor C Minggu',
        'anchor_strategy' => 'calendar_anchored',
        'anchor_day_type' => 'week',
        'anchor_config' => ['day_of_week' => 3],
    ])->assertRedirect();

    $template = TaskTemplate::where('title', 'Anchor C Minggu')->firstOrFail();
    expect($template->anchor_strategy)->toBe('calendar_anchored');
    expect($template->anchor_config)->toBe(['day_of_week' => 3]);
    // F-163: interval TIDAK diwajibkan/disimpan untuk calendar_anchored.
    expect($template->interval_value)->toBeNull();
    expect($template->interval_unit)->toBeNull();
});

test('D3d: simpan anchor C tanggal-dalam-bulan -> anchor_config={day_of_month} SAJA (F-74, bukan keduanya)', function () {
    $admin = User::factory()->admin()->create();
    $project = createConfigFormProject($admin);

    $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        ...baseTemplatePayload(),
        'title' => 'Anchor C Bulan',
        'anchor_strategy' => 'calendar_anchored',
        'anchor_day_type' => 'month',
        'anchor_config' => ['day_of_week' => 1, 'day_of_month' => 15], // day_of_week SENGAJA ikut terkirim (bug form lama), harus diabaikan
    ])->assertRedirect();

    $template = TaskTemplate::where('title', 'Anchor C Bulan')->firstOrFail();
    expect($template->anchor_config)->toBe(['day_of_month' => 15]); // day_of_week TIDAK ikut tersimpan
});

test('D3e: guard date_window_config + max_active_instances tersimpan', function () {
    $admin = User::factory()->admin()->create();
    $project = createConfigFormProject($admin);

    $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        ...baseTemplatePayload(),
        'title' => 'Guard Test',
        'anchor_strategy' => 'time_based',
        'interval_value' => 1,
        'interval_unit' => 'day',
        'date_window_config' => ['weekdays' => [1, 2, 3], 'dom_min' => 5, 'dom_max' => 25],
        'max_active_instances' => 3,
    ])->assertRedirect();

    $template = TaskTemplate::where('title', 'Guard Test')->firstOrFail();
    // toEqual (bukan toBe): kolom JSON MySQL tidak menjamin urutan key saat
    // round-trip decode -- isi yang dibandingkan, bukan urutan key.
    expect($template->date_window_config)->toEqual(['weekdays' => [1, 2, 3], 'dom_min' => 5, 'dom_max' => 25]);
    expect($template->max_active_instances)->toBe(3);
});

test('D3f: validasi tolak anchor_strategy ngawur', function () {
    $admin = User::factory()->admin()->create();
    $project = createConfigFormProject($admin);

    $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        ...baseTemplatePayload(),
        'title' => 'Invalid Anchor',
        'anchor_strategy' => 'sihir',
    ])->assertSessionHasErrors('anchor_strategy');

    expect(TaskTemplate::where('title', 'Invalid Anchor')->exists())->toBeFalse();
});

test('D3f: validasi tolak interval_value=0 untuk anchor A', function () {
    $admin = User::factory()->admin()->create();
    $project = createConfigFormProject($admin);

    $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        ...baseTemplatePayload(),
        'title' => 'Invalid Interval',
        'anchor_strategy' => 'time_based',
        'interval_value' => 0,
        'interval_unit' => 'day',
    ])->assertSessionHasErrors('interval_value');
});

test('D3f: validasi tolak anchor_day_of_week di luar 1-7', function () {
    $admin = User::factory()->admin()->create();
    $project = createConfigFormProject($admin);

    $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        ...baseTemplatePayload(),
        'title' => 'Invalid Day Of Week',
        'anchor_strategy' => 'calendar_anchored',
        'anchor_day_type' => 'week',
        'anchor_config' => ['day_of_week' => 8],
    ])->assertSessionHasErrors('anchor_config.day_of_week');
});

test('D3f: validasi tolak calendar_anchored TANPA anchor_day_type (F-74 -- wajib pilih radio)', function () {
    $admin = User::factory()->admin()->create();
    $project = createConfigFormProject($admin);

    $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        ...baseTemplatePayload(),
        'title' => 'Invalid No Day Type',
        'anchor_strategy' => 'calendar_anchored',
    ])->assertSessionHasErrors('anchor_day_type');
});

test('D3f: validasi tolak dom_max < dom_min', function () {
    $admin = User::factory()->admin()->create();
    $project = createConfigFormProject($admin);

    $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        ...baseTemplatePayload(),
        'title' => 'Invalid Dom Range',
        'anchor_strategy' => 'time_based',
        'interval_value' => 1,
        'interval_unit' => 'day',
        'date_window_config' => ['dom_min' => 20, 'dom_max' => 5],
    ])->assertSessionHasErrors('date_window_config.dom_max');
});

test('D5: member tanpa task.manage tetap ditolak walau kirim field automation lengkap (F-90/F-95 regresi)', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['organization_id' => $admin->organization_id]);
    $project = createConfigFormProject($admin);
    $project->members()->attach($member->id);

    $this->actingAs($member)->post(route('task-templates.store', $project->id), [
        ...baseTemplatePayload(),
        'title' => 'Member Coba Bikin',
        'anchor_strategy' => 'time_based',
        'interval_value' => 1,
        'interval_unit' => 'day',
    ])->assertForbidden();

    expect(TaskTemplate::where('title', 'Member Coba Bikin')->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// D4: END-TO-END -- "Boss atur lewat form -> engine PATUH"
// ---------------------------------------------------------------------------

test('D4: form interval=3 hari -> engine generate PERSIS tiap 3 hari', function () {
    $admin = User::factory()->admin()->create();
    $project = createConfigFormProject($admin);
    seedConfigFormSchedule($admin);

    $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        ...baseTemplatePayload(),
        'title' => 'E2E Interval 3 Hari',
        'anchor_strategy' => 'time_based',
        'interval_value' => 3,
        'interval_unit' => 'day',
    ])->assertRedirect();

    $template = TaskTemplate::where('title', 'E2E Interval 3 Hari')->firstOrFail();

    // Senin 3 s/d Kamis 6 Agustus 2026 -- 4 hari kerja berturut, TANPA weekend
    // di tengah supaya "tiap 3 hari" murni terlihat tanpa gangguan shift libur.
    foreach (['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06'] as $day) {
        $this->travelTo(Carbon::parse($day)->setTime(0, 1));
        $this->artisan('automation:run')->assertSuccessful();
    }

    $dueDates = Task::where('task_template_id', $template->id)
        ->get()->map(fn (Task $t) => $t->due_date->toDateString())->sort()->values()->all();

    // Generate SENIN (3), lalu SKIP Selasa/Rabu (belum 3 hari), generate lagi KAMIS (6).
    expect($dueDates)->toBe(['2026-08-03', '2026-08-06']);
});

test('D4: form hari-tetap Rabu (calendar_anchored) -> engine generate HANYA di Rabu', function () {
    $admin = User::factory()->admin()->create();
    $project = createConfigFormProject($admin);
    seedConfigFormSchedule($admin);

    $this->actingAs($admin)->post(route('task-templates.store', $project->id), [
        ...baseTemplatePayload(),
        'title' => 'E2E Hari Tetap Rabu',
        'anchor_strategy' => 'calendar_anchored',
        'anchor_day_type' => 'week',
        'anchor_config' => ['day_of_week' => 3], // Rabu
    ])->assertRedirect();

    $template = TaskTemplate::where('title', 'E2E Hari Tetap Rabu')->firstOrFail();

    // Senin 3 s/d Jumat 7 Agustus 2026 -- Rabu jatuh di 5 Agustus.
    foreach (['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07'] as $day) {
        $this->travelTo(Carbon::parse($day)->setTime(0, 1));
        $this->artisan('automation:run')->assertSuccessful();
    }

    $dueDates = Task::where('task_template_id', $template->id)
        ->get()->map(fn (Task $t) => $t->due_date->toDateString())->all();

    expect($dueDates)->toBe(['2026-08-05']); // HANYA Rabu
});

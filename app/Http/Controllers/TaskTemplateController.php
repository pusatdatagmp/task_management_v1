<?php

/**
 * ==========================================================
 * MODUL       : TaskTemplateController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : CRUD blueprint recurring task per project (F-46, admin only,
 *               task.manage). Engine yang MELAHIRKAN instance ada terpisah —
 *               `RunAutomationEngineCommand` (AE-2/3, aktif) & `GenerateRecurringTasksCommand`
 *               (@deprecated F-162) — controller ini cuma kelola blueprint-nya
 *               (identitas + jadwal + default assignee + AE-2b: konfigurasi
 *               Automation Engine anchor A/B/C + guard).
 * DIPANGGIL   : routes/admin.php
 * MEMANGGIL   : TaskTemplate, Project
 * DATA MASUK  : Form Template CRUD
 * DATA KELUAR : Inertia pages 'task-templates/*'
 * RISIKO      : SUMBER : A6 — update() TIDAK PERNAH menyentuh tasks yang sudah
 *               tergenerate dari template ini (instance independen setelah lahir,
 *               F-46). JANGAN tambahkan cascading update ke $taskTemplate->tasks()
 *               di sini. Tidak ada destroy() di controller ini SENGAJA — deaktivasi
 *               lewat toggleActive() (pola sama UserController, F-16-style: jangan
 *               hilangkan blueprint yang sudah pernah melahirkan instance nyata).
 *               AE-2b: field automation (anchor_strategy dkk) MURNI CRUD ke
 *               kolom yang sudah ada — normalizeAutomationConfig() TIDAK
 *               mengevaluasi jadwal apa pun, itu tugas Pipeline (AE-2/3).
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\TaskTemplate\StoreTaskTemplateRequest;
use App\Http\Requests\TaskTemplate\UpdateTaskTemplateRequest;
use App\Models\Project;
use App\Models\TaskTemplate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TaskTemplateController extends Controller
{
    /**
     * KONTRAK: field mentah dari request yang TIDAK boleh ikut spread langsung
     * ke TaskTemplate::create()/update() -- semuanya dinormalisasi dulu lewat
     * normalizeAutomationConfig() (AE-2b). `anchor_day_type` BUKAN kolom
     * database sama sekali (murni diskriminator radio F-74 di form), kalau
     * ikut spread akan meledak "unknown column" pada create()/update().
     */
    private const AUTOMATION_FIELDS = [
        'anchor_strategy', 'interval_value', 'interval_unit',
        'anchor_config', 'anchor_day_type', 'date_window_config', 'max_active_instances',
    ];

    public function index(Project $project): Response
    {
        return Inertia::render('task-templates/index', [
            'project' => $project->only(['id', 'name']),
            'templates' => $project->taskTemplates()->orderBy('title')->get(),
        ]);
    }

    /**
     * BUSINESS RULE: v1.2 H7b (F-140/F-144) — "Tugas Berulang" flat lintas SEMUA
     * project (nav sebelumnya disabled, F-147). CRUD asli TETAP per-project (F-46
     * tidak berubah) — halaman ini MURNI listing+navigasi baru, link Edit/Aktifkan
     * mengarah ke route project-scoped yang sudah ada, nol endpoint baru.
     */
    public function allProjects(): Response
    {
        return Inertia::render('task-templates/all', [
            'templates' => TaskTemplate::with('project:id,name')
                ->orderBy('project_id')
                ->orderBy('title')
                ->get(),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Project $project): Response
    {
        return Inertia::render('task-templates/create', [
            'project' => $project->only(['id', 'name']),
            'members' => $project->members()->select('users.id', 'users.name')->orderBy('users.name')->get(),
        ]);
    }

    public function store(StoreTaskTemplateRequest $request, Project $project): RedirectResponse
    {
        $template = TaskTemplate::create([
            ...$request->safe()->except([...self::AUTOMATION_FIELDS, 'recurrence_config', 'checklist_items']),
            'organization_id' => $project->organization_id,
            'project_id' => $project->id,
            // BUSINESS RULE A4: daily -> config diabaikan, dipaksa [] di sini
            // terlepas dari apa yang dikirim form (bentuknya cuma relevan untuk
            // weekly/monthly, lihat StoreTaskTemplateRequest).
            'recurrence_config' => $this->normalizeRecurrenceConfig(
                $request->validated('task_type'),
                $request->validated('recurrence_config') ?? []
            ),
            ...$this->normalizeAutomationConfig($request->validated()),
        ]);

        $this->syncChecklistItems($template, $request->validated('checklist_items') ?? []);

        return to_route('task-templates.index', $project);
    }

    public function edit(Project $project, TaskTemplate $taskTemplate): Response
    {
        $taskTemplate->load('checklistItems');

        return Inertia::render('task-templates/edit', [
            'project' => $project->only(['id', 'name']),
            'template' => [
                ...$taskTemplate->toArray(),
                'checklist_items' => $taskTemplate->checklistItems->pluck('text'),
            ],
            'members' => $project->members()->select('users.id', 'users.name')->orderBy('users.name')->get(),
        ]);
    }

    public function update(UpdateTaskTemplateRequest $request, Project $project, TaskTemplate $taskTemplate): RedirectResponse
    {
        $taskTemplate->update([
            ...$request->safe()->except([...self::AUTOMATION_FIELDS, 'recurrence_config', 'checklist_items']),
            'recurrence_config' => $this->normalizeRecurrenceConfig(
                $request->validated('task_type'),
                $request->validated('recurrence_config') ?? []
            ),
            ...$this->normalizeAutomationConfig($request->validated()),
        ]);

        // SUMBER: 'checklist_items' bersifat 'sometimes' (opsional) di request —
        // HANYA sync kalau key ini BENAR-BENAR dikirim. Kalau tidak (caller lama/
        // API lain yang belum tahu field ini), checklist yang sudah ada TIDAK
        // disentuh sama sekali — beda dari store() yang aman pakai default [] karena
        // template baru dijamin belum punya checklist apa pun untuk diwipe.
        if ($request->has('checklist_items')) {
            $this->syncChecklistItems($taskTemplate, $request->validated('checklist_items'));
        }

        return to_route('task-templates.index', $project);
    }

    /**
     * BUSINESS RULE F-123: blueprint checklist BUKAN daftar bertumbuh (append) —
     * form template kirim SELURUH daftar tiap simpan, jadi cara paling jujur
     * merepresentasikannya adalah HAPUS-lalu-BUAT-ULANG (bukan diff), pola sama
     * TaskTemplateController::update() untuk recurrence_config (seluruh bentuk
     * dikirim ulang, bukan di-patch sebagian). TIDAK menyentuh task_checklist_items
     * milik instance yang sudah lahir (A6/F-46 — beda tabel, beda baris sama sekali).
     *
     * @param  array<int, string>  $texts
     */
    private function syncChecklistItems(TaskTemplate $template, array $texts): void
    {
        $template->checklistItems()->delete();

        foreach (array_values($texts) as $position => $text) {
            $template->checklistItems()->create([
                'organization_id' => $template->organization_id,
                'text' => $text,
                'position' => $position,
            ]);
        }
    }

    /**
     * BUSINESS RULE A5: is_active=false -> berhenti generate KE DEPAN saja.
     * Instance yang sudah lahir sebelumnya TETAP ada (F-46) — toggle ini tidak
     * menyentuh tabel tasks sama sekali.
     */
    public function toggleActive(Project $project, TaskTemplate $taskTemplate): RedirectResponse
    {
        $taskTemplate->update(['is_active' => ! $taskTemplate->is_active]);

        return back();
    }

    private function normalizeRecurrenceConfig(string $taskType, array $config): array
    {
        return match ($taskType) {
            'weekly' => ['day_of_week' => (int) $config['day_of_week']],
            'monthly' => ['day_of_month' => (int) $config['day_of_month']],
            default => [], // daily
        };
    }

    /**
     * KONTRAK: AE-2b (F-158) — normalisasi 6 kolom Automation Engine dari
     * request tervalidasi. Form CRUD MURNI: kolom ini sudah ada sejak AE-1,
     * dibaca Pipeline/Guard/Strategy AE-2/AE-3 — TIDAK ADA logika evaluasi
     * baru ditambahkan di sini, hanya bentuk data yang dirapikan sebelum simpan.
     *
     * BUSINESS RULE: interval_value/interval_unit HANYA relevan untuk anchor
     * time_based/completion_based (F-163) -- dipaksa NULL untuk calendar_anchored
     * supaya tidak ada sisa interval basi dari strategy sebelumnya (mis. Boss
     * ganti dari time_based ke calendar_anchored, interval lama TIDAK boleh
     * nyangkut, walau TimeDeltaGuard sudah null-safe terhadap ini juga).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeAutomationConfig(array $validated): array
    {
        $anchorStrategy = $validated['anchor_strategy'];
        $needsInterval = in_array($anchorStrategy, ['time_based', 'completion_based'], true);

        return [
            'anchor_strategy' => $anchorStrategy,
            'interval_value' => $needsInterval ? (int) $validated['interval_value'] : null,
            'interval_unit' => $needsInterval ? $validated['interval_unit'] : null,
            'anchor_config' => $anchorStrategy === 'calendar_anchored'
                ? $this->normalizeAnchorConfig($validated['anchor_day_type'] ?? null, $validated['anchor_config'] ?? [])
                : null,
            'date_window_config' => $this->normalizeDateWindowConfig($validated['date_window_config'] ?? []),
            'max_active_instances' => $validated['max_active_instances'] ?? null,
        ];
    }

    /**
     * BUSINESS RULE F-74: `anchor_day_type` adalah RADIO (week|month) di form --
     * hasilnya SELALU tepat 1 key di anchor_config, tidak pernah 0 atau 2.
     */
    private function normalizeAnchorConfig(?string $dayType, array $config): array
    {
        return match ($dayType) {
            'week' => ['day_of_week' => (int) $config['day_of_week']],
            'month' => ['day_of_month' => (int) $config['day_of_month']],
            default => [],
        };
    }

    /**
     * KONTRAK: DateWindowGuard (F-161 B3) membaca `empty($config)` sebagai
     * "tak ada batasan" -- array kosong dari form (tidak ada weekdays dicentang
     * DAN dom_min/dom_max kosong) dinormalisasi jadi [] eksplisit di sini,
     * konsisten dengan kontrak guard itu, bukan menyimpan struktur setengah
     * terisi yang membingungkan saat dibaca ulang di form edit.
     */
    private function normalizeDateWindowConfig(array $config): array
    {
        $weekdays = array_values(array_map('intval', $config['weekdays'] ?? []));

        return array_filter([
            'weekdays' => $weekdays ?: null,
            'dom_min' => $config['dom_min'] ?? null,
            'dom_max' => $config['dom_max'] ?? null,
        ], fn ($value) => $value !== null);
    }
}

<?php

/**
 * ==========================================================
 * MODUL       : TaskTemplateController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : CRUD blueprint recurring task per project (F-46, admin only,
 *               task.manage). Engine yang MELAHIRKAN instance ada terpisah di
 *               GenerateRecurringTasksCommand — controller ini cuma kelola
 *               blueprint-nya (identitas + jadwal + default assignee).
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
    public function index(Project $project): Response
    {
        return Inertia::render('task-templates/index', [
            'project' => $project->only(['id', 'name']),
            'templates' => $project->taskTemplates()->orderBy('title')->get(),
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
            ...$request->safe()->except(['recurrence_config', 'checklist_items']),
            'organization_id' => $project->organization_id,
            'project_id' => $project->id,
            // BUSINESS RULE A4: daily -> config diabaikan, dipaksa [] di sini
            // terlepas dari apa yang dikirim form (bentuknya cuma relevan untuk
            // weekly/monthly, lihat StoreTaskTemplateRequest).
            'recurrence_config' => $this->normalizeRecurrenceConfig(
                $request->validated('task_type'),
                $request->validated('recurrence_config') ?? []
            ),
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
            ...$request->safe()->except(['recurrence_config', 'checklist_items']),
            'recurrence_config' => $this->normalizeRecurrenceConfig(
                $request->validated('task_type'),
                $request->validated('recurrence_config') ?? []
            ),
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
}

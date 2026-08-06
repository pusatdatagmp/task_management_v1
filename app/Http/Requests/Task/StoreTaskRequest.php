<?php

/**
 * ==========================================================
 * MODUL       : StoreTaskRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi buat task baru (admin only, F-29). task_type dibatasi ke
 *               tentative|project — daily/weekly/monthly lahir dari task_templates
 *               via recurring engine v0.8 (Hari-4 §D3), bukan dari form ini.
 * DIPANGGIL   : TaskController::store()
 * MEMANGGIL   : Task::booted() guard subtask 1-level (via aturan custom di bawah)
 * DATA MASUK  : Form Task CRUD — project dari route model binding
 * DATA KELUAR : Data tervalidasi -> TaskController::store()
 * RISIKO      : SUMBER : F-31 — due_date WAJIB, TIDAK ADA default di sini maupun di
 *               model (F-68). assignees/parent_task_id WAJIB di-scope ke project ini
 *               supaya admin tidak bisa assign user/parent dari project/organisasi lain.
 * ==========================================================
 */

namespace App\Http\Requests\Task;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('task.manage'); // F-90
    }

    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'task_type' => ['required', Rule::in(['tentative', 'project'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            // F-122/F-126: quadrant Eisenhower TERPISAH dari `priority` enum lama.
            // Nullable — task lama/baru yang belum diklasifikasi = "belum diklasifikasi",
            // BUKAN dipaksa p4 (lihat header migrasi kolom ini).
            'priority_quadrant' => ['nullable', Rule::in(['p1', 'p2', 'p3', 'p4'])],
            'estimated_minutes' => ['required', 'integer', 'min:1'],
            'points' => ['required', 'integer', 'min:0'],
            'due_date' => ['required', 'date'],
            'assignees' => ['nullable', 'array'],
            'assignees.*' => [Rule::exists('project_user', 'user_id')->where('project_id', $project->id)],
            'parent_task_id' => [
                'nullable',
                Rule::exists('tasks', 'id')->where('project_id', $project->id)->whereNull('deleted_at'),
            ],
            // Revisi 2026-08-06 item 5 — checklist ("subtask" ringan, F-123) bisa
            // diisi LANGSUNG saat buat task, pola identik StoreTaskTemplateRequest.
            'checklist_items' => ['sometimes', 'array'],
            'checklist_items.*' => ['string', 'max:500'],
        ];
    }

    /**
     * BUSINESS RULE: F-20 — subtask maksimal 1 level. Task::booted() sudah menjaga
     * ini di level model (lempar InvalidArgumentException), tapi divalidasi juga di
     * sini supaya admin dapat pesan error form yang rapi, bukan 500 dari model.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $parentId = $this->input('parent_task_id');

            if (! $parentId) {
                return;
            }

            $parentIsSubtask = Task::whereKey($parentId)->whereNotNull('parent_task_id')->exists();

            if ($parentIsSubtask) {
                $validator->errors()->add('parent_task_id', 'Subtask maksimal 1 level (F-20) — task yang dipilih sudah jadi subtask.');
            }
        });
    }
}

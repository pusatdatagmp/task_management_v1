<?php

/**
 * ==========================================================
 * MODUL       : UpdateTaskTemplateRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi edit template recurring (admin only, task.manage). Field
 *               identik Store — edit template TIDAK PERNAH menyentuh instance tasks
 *               yang sudah lahir (A6, F-46: template != task).
 * DIPANGGIL   : TaskTemplateController::update()
 * MEMANGGIL   : -
 * DATA MASUK  : Form edit template — project & template dari route model binding
 * DATA KELUAR : Data tervalidasi -> TaskTemplateController::update()
 * RISIKO      : SUMBER : F-86 — sama seperti Store, default_assignees.* divalidasi
 *               ulang sebagai member project SAAT INI (bisa beda dari saat create).
 * ==========================================================
 */

namespace App\Http\Requests\TaskTemplate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateTaskTemplateRequest extends FormRequest
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
            'task_type' => ['required', Rule::in(['daily', 'weekly', 'monthly'])], // F-46/A2
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'estimated_minutes' => ['required', 'integer', 'min:1'],
            'points' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],

            'recurrence_config' => ['array'],
            'recurrence_config.day_of_week' => ['required_if:task_type,weekly', 'integer', 'between:1,7'],
            'recurrence_config.day_of_month' => ['required_if:task_type,monthly', 'integer', 'between:1,31'],

            'default_assignees' => ['present', 'array'], // F-86
            'default_assignees.*' => [Rule::exists('project_user', 'user_id')->where('project_id', $project->id)],

            // F-123/F-127: lihat StoreTaskTemplateRequest — 'sometimes' (opsional,
            // absen = tidak diubah/kosong), disalin ke instance baru saat generate,
            // TIDAK menyentuh instance yang sudah lahir (A6/F-46).
            'checklist_items' => ['sometimes', 'array'],
            'checklist_items.*' => ['string', 'max:500'],
        ];
    }
}

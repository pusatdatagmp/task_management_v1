<?php

/**
 * ==========================================================
 * MODUL       : UpdateTaskTemplateRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi edit template recurring (admin only, task.manage). Field
 *               identik Store — edit template TIDAK PERNAH menyentuh instance tasks
 *               yang sudah lahir (A6, F-46: template != task). AE-2b: field
 *               automation identik StoreTaskTemplateRequest (lihat komentar di sana).
 *               Revisi 2026-08-07: `task_type`/`recurrence_config` dicabut dari
 *               validasi, lihat StoreTaskTemplateRequest untuk alasannya.
 * DIPANGGIL   : TaskTemplateController::update()
 * MEMANGGIL   : -
 * DATA MASUK  : Form edit template — project & template dari route model binding
 * DATA KELUAR : Data tervalidasi -> TaskTemplateController::update()
 * RISIKO      : SUMBER : F-86 — sama seperti Store, default_assignees.* divalidasi
 *               ulang sebagai member project SAAT INI (bisa beda dari saat create).
 *               Ganti anchor_strategy di template yang SUDAH pernah generate
 *               (last_generated_date/blocked_since terisi) TIDAK di-reset di sini
 *               SENGAJA — run berikutnya otomatis mengevaluasi ulang dengan
 *               strategy baru, F-46 filosofi "template != instance yang sudah lahir".
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
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'estimated_minutes' => ['required', 'integer', 'min:1'],
            'points' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            // Revisi 2026-08-06 item 7 — lihat StoreTaskTemplateRequest.
            'due_offset_days' => ['nullable', 'integer', 'min:1', 'max:365'],

            'default_assignees' => ['present', 'array'], // F-86
            'default_assignees.*' => [Rule::exists('project_user', 'user_id')->where('project_id', $project->id)],

            // F-123/F-127: lihat StoreTaskTemplateRequest — 'sometimes' (opsional,
            // absen = tidak diubah/kosong), disalin ke instance baru saat generate,
            // TIDAK menyentuh instance yang sudah lahir (A6/F-46).
            'checklist_items' => ['sometimes', 'array'],
            'checklist_items.*' => ['string', 'max:500'],

            // AE-2b: identik StoreTaskTemplateRequest, lihat komentar di sana (F-158/F-74/F-163).
            'anchor_strategy' => ['required', Rule::in(['time_based', 'completion_based', 'calendar_anchored'])],
            'interval_value' => ['required_if:anchor_strategy,time_based,completion_based', 'nullable', 'integer', 'min:1'],
            'interval_unit' => ['required_if:anchor_strategy,time_based,completion_based', 'nullable', Rule::in(['day', 'week', 'month'])],
            'anchor_day_type' => ['required_if:anchor_strategy,calendar_anchored', 'nullable', Rule::in(['week', 'month'])],
            'anchor_config' => ['array'],
            'anchor_config.day_of_week' => ['required_if:anchor_day_type,week', 'nullable', 'integer', 'between:1,7'],
            'anchor_config.day_of_month' => ['required_if:anchor_day_type,month', 'nullable', 'integer', 'between:1,31'],
            'date_window_config' => ['array'],
            'date_window_config.weekdays' => ['array'],
            'date_window_config.weekdays.*' => ['integer', 'between:1,7'],
            'date_window_config.dom_min' => ['nullable', 'integer', 'between:1,31'],
            'date_window_config.dom_max' => ['nullable', 'integer', 'between:1,31', 'gte:date_window_config.dom_min'],
            'max_active_instances' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

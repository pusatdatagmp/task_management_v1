<?php

/**
 * ==========================================================
 * MODUL       : StoreTaskTemplateRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi buat template recurring baru (admin only, task.manage).
 *               task_type dibatasi ke daily|weekly|monthly (F-46/A2) — tentative|project
 *               TIDAK berulang, ditolak otomatis lewat Rule::in di sini, bukan lewat
 *               cek terpisah.
 * DIPANGGIL   : TaskTemplateController::store()
 * MEMANGGIL   : -
 * DATA MASUK  : Form Template CRUD — project dari route model binding
 * DATA KELUAR : Data tervalidasi -> TaskTemplateController::store()
 * RISIKO      : SUMBER : F-86 — default_assignees.* WAJIB divalidasi sebagai member
 *               project ini SAAT SIMPAN. Member project bisa berubah lagi setelah
 *               template dibuat — validasi ulang WAJIB terjadi lagi saat generate
 *               (GenerateRecurringTasksCommand), bukan cuma di sini.
 * ==========================================================
 */

namespace App\Http\Requests\TaskTemplate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreTaskTemplateRequest extends FormRequest
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
            // BUSINESS RULE F-46/A2: hanya 3 tipe ini yang berulang. Kirim
            // tentative/project -> ditolak di sini (bukan "diabaikan diam-diam").
            'task_type' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'estimated_minutes' => ['required', 'integer', 'min:1'],
            'points' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],

            // BUSINESS RULE A4: bentuk recurrence_config beda per task_type. daily
            // TIDAK divalidasi di sini (A4: "config kosong/diabaikan") — controller
            // yang menormalkan jadi [] terlepas dari apa yang dikirim.
            'recurrence_config' => ['array'],
            'recurrence_config.day_of_week' => ['required_if:task_type,weekly', 'integer', 'between:1,7'],
            'recurrence_config.day_of_month' => ['required_if:task_type,monthly', 'integer', 'between:1,31'],

            // F-86: default_assignees WAJIB project member SAAT SIMPAN. 'present'
            // (bukan 'nullable') -- kolom DB tidak nullable (array kosong tetap
            // valid JSON), jadi field ini wajib dikirim walau isinya [].
            'default_assignees' => ['present', 'array'],
            'default_assignees.*' => [Rule::exists('project_user', 'user_id')->where('project_id', $project->id)],

            // F-123/F-127: blueprint checklist yang disalin ke tiap instance saat
            // generate (GenerateRecurringTasksCommand). 'sometimes' (BUKAN 'present'
            // seperti default_assignees) — field baru ini OPSIONAL, absen = checklist
            // kosong (F-127: kosong tetap lolos gate), supaya caller lama yang belum
            // tahu field ini (TaskTemplateTest existing) tidak ditolak validasi.
            'checklist_items' => ['sometimes', 'array'],
            'checklist_items.*' => ['string', 'max:500'],
        ];
    }
}

<?php

/**
 * ==========================================================
 * MODUL       : StoreTaskTemplateRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi buat template recurring baru (admin only, task.manage).
 *               task_type dibatasi ke daily|weekly|monthly (F-46/A2) — tentative|project
 *               TIDAK berulang, ditolak otomatis lewat Rule::in di sini, bukan lewat
 *               cek terpisah. AE-2b (F-158): field automation (anchor_strategy dkk)
 *               ditambahkan DI SINI — form CRUD murni ke kolom yang sudah ada sejak
 *               AE-1, TIDAK ada jalur logika evaluasi baru (Pipeline AE-2/3 tetap
 *               satu-satunya pembaca).
 * DIPANGGIL   : TaskTemplateController::store()
 * MEMANGGIL   : -
 * DATA MASUK  : Form Template CRUD — project dari route model binding
 * DATA KELUAR : Data tervalidasi -> TaskTemplateController::store()
 * RISIKO      : SUMBER : F-86 — default_assignees.* WAJIB divalidasi sebagai member
 *               project ini SAAT SIMPAN. Member project bisa berubah lagi setelah
 *               template dibuat — validasi ulang WAJIB terjadi lagi saat generate
 *               (GenerateRecurringTasksCommand), bukan cuma di sini.
 *               F-74: "tepat 1 dari day_of_week/day_of_month" ditegakkan lewat
 *               `anchor_day_type` (RADIO week|month), BUKAN 2 field opsional
 *               independen — structural, bukan validasi penolak belakangan.
 *               interval_value/interval_unit TIDAK diwajibkan untuk
 *               calendar_anchored (F-163: hari-tetap sudah cukup membatasi,
 *               lihat TimeDeltaGuard) — HANYA wajib untuk time_based/completion_based.
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

            // -----------------------------------------------------------------
            // AE-2b (F-158): konfigurasi Automation Engine — Boss atur "tiap N
            // hari/minggu/bulan" (A), "tunggu selesai" (B), atau "hari tetap" (C)
            // sendiri lewat form ini. Engine (Pipeline/Guard/Strategy AE-2/3)
            // MEMBACA kolom-kolom ini, TIDAK ADA jalur logika baru di sini.
            // -----------------------------------------------------------------
            'anchor_strategy' => ['required', Rule::in(['time_based', 'completion_based', 'calendar_anchored'])],

            // A (time_based) & B (completion_based) WAJIB interval -- TimeDeltaGuard
            // butuh ini sebagai due-line (F-152). C (calendar_anchored) TIDAK --
            // hari-tetap dari anchor_config sudah cukup (TimeDeltaGuard Pass
            // langsung kalau interval_unit null, lihat komentar guard itu).
            'interval_value' => ['required_if:anchor_strategy,time_based,completion_based', 'nullable', 'integer', 'min:1'],
            'interval_unit' => ['required_if:anchor_strategy,time_based,completion_based', 'nullable', Rule::in(['day', 'week', 'month'])],

            // F-74: RADIO (bukan 2 field independen) -- structural mencegah state
            // "keduanya terisi" atau "keduanya kosong" untuk calendar_anchored.
            'anchor_day_type' => ['required_if:anchor_strategy,calendar_anchored', 'nullable', Rule::in(['week', 'month'])],
            'anchor_config' => ['array'],
            'anchor_config.day_of_week' => ['required_if:anchor_day_type,week', 'nullable', 'integer', 'between:1,7'],
            'anchor_config.day_of_month' => ['required_if:anchor_day_type,month', 'nullable', 'integer', 'between:1,31'],

            // Guard OPSIONAL, berlaku LEPAS dari anchor_strategy manapun --
            // DateWindowGuard/QuotaGuard generik (F-161 B3/B4).
            'date_window_config' => ['array'],
            'date_window_config.weekdays' => ['array'],
            'date_window_config.weekdays.*' => ['integer', 'between:1,7'],
            'date_window_config.dom_min' => ['nullable', 'integer', 'between:1,31'],
            'date_window_config.dom_max' => ['nullable', 'integer', 'between:1,31', 'gte:date_window_config.dom_min'],
            'max_active_instances' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

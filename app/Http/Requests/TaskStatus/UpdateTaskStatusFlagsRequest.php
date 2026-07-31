<?php

/**
 * ==========================================================
 * MODUL       : UpdateTaskStatusFlagsRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi submit RADIO/CHECKBOX di halaman index (F-74, Hari-5 §B) —
 *               satu form untuk SEMUA status project sekaligus, bukan per-baris.
 * DIPANGGIL   : TaskStatusController::updateFlags()
 * MEMANGGIL   : -
 * DATA MASUK  : is_completed_id (radio, wajib), is_review_id (radio, boleh kosong),
 *               work_state_ids[] (checkbox, minimal 1) — semua di-scope ke project ini
 * DATA KELUAR : Data tervalidasi -> TaskStatusController::updateFlags()
 * RISIKO      : SUMBER : F-74 — is_completed_id 'required' + exists() secara STRUKTUR
 *               menjamin tepat 1 terpilih (radio HTML tidak bisa kirim 2 value untuk
 *               name yang sama, dan browser tidak akan submit form kalau radio
 *               required belum dipilih). work_state_ids 'min:1' menjamin minimal 1
 *               dicentang. Tidak ada lagi kelas bug "0 atau 2 completed" — radio
 *               menghapusnya di level struktur, bukan validasi tambahan.
 * ==========================================================
 */

namespace App\Http\Requests\TaskStatus;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateTaskStatusFlagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('status.manage'); // F-90
    }

    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'is_completed_id' => ['required', Rule::exists('task_statuses', 'id')->where('project_id', $project->id)],
            'is_review_id' => ['nullable', Rule::exists('task_statuses', 'id')->where('project_id', $project->id)],
            'work_state_ids' => ['required', 'array', 'min:1'],
            'work_state_ids.*' => [Rule::exists('task_statuses', 'id')->where('project_id', $project->id)],
        ];
    }
}

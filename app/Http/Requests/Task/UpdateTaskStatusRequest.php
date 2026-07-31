<?php

/**
 * ==========================================================
 * MODUL       : UpdateTaskStatusRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi input endpoint ubah status "biasa" (F-45) — dipakai admin
 *               MAUPUN member (member cuma boleh untuk task yang di-assign ke dia,
 *               dicek di TaskTransitionService, bukan di sini).
 * DIPANGGIL   : TaskController::updateStatus()
 * MEMANGGIL   : -
 * DATA MASUK  : task_status_id — task dari route model binding
 * DATA KELUAR : Data tervalidasi -> TaskTransitionService::changeStatus()
 * RISIKO      : authorize() SENGAJA hanya cek "sudah login" — pengecekan assignee
 *               (F-29: member cuma boleh task sendiri) ada di service layer supaya
 *               satu tempat saja yang menegakkan aturan itu (dipakai juga oleh test).
 * ==========================================================
 */

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $task = $this->route('task');

        return [
            'task_status_id' => ['required', Rule::exists('task_statuses', 'id')->where('project_id', $task->project_id)],
        ];
    }
}

<?php

/**
 * ==========================================================
 * MODUL       : UpdateTaskStatusRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi rename/ubah warna SATU status (F-44). 3 flag TIDAK diubah
 *               lewat sini lagi — lihat UpdateTaskStatusFlagsRequest.
 * DIPANGGIL   : TaskStatusController::update()
 * MEMANGGIL   : -
 * DATA MASUK  : Form edit status (name, color) — status dari route model binding
 * DATA KELUAR : Data tervalidasi -> TaskStatusController::update()
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Http\Requests\TaskStatus;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('status.manage'); // F-90
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}

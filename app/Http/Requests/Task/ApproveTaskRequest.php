<?php

/**
 * ==========================================================
 * MODUL       : ApproveTaskRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi approve task di status review (admin only, F-28).
 *               quality_rating WAJIB diisi (RAW KPI — F-37 #4).
 * DIPANGGIL   : TaskController::approve()
 * MEMANGGIL   : -
 * DATA MASUK  : quality_rating (1-5) — task dari route model binding
 * DATA KELUAR : Data tervalidasi -> TaskTransitionService::approve()
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ApproveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('task.approve'); // F-90/F-28
    }

    public function rules(): array
    {
        return [
            'quality_rating' => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }
}

<?php

/**
 * ==========================================================
 * MODUL       : ApproveExtensionRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi approve perpanjangan deadline (admin only, F-50/matriks
 *               BF §6 "Approve extension"). review_note opsional (bukan wajib
 *               seperti reject — approve tidak butuh alasan penolakan).
 * DIPANGGIL   : DeadlineExtensionController::approve()
 * MEMANGGIL   : -
 * DATA MASUK  : review_note opsional — extension dari route model binding
 * DATA KELUAR : Data tervalidasi -> DeadlineExtensionController::approve()
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Http\Requests\DeadlineExtension;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ApproveExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('task.approve'); // F-90, sama dengan approve task (F-28)
    }

    public function rules(): array
    {
        return [
            'review_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

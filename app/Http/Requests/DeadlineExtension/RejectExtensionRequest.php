<?php

/**
 * ==========================================================
 * MODUL       : RejectExtensionRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi reject perpanjangan deadline (admin only). review_note
 *               WAJIB — pemohon berhak tahu alasan penolakan (trigger #10, F-35).
 * DIPANGGIL   : DeadlineExtensionController::reject()
 * MEMANGGIL   : -
 * DATA MASUK  : review_note wajib — extension dari route model binding
 * DATA KELUAR : Data tervalidasi -> DeadlineExtensionController::reject()
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Http\Requests\DeadlineExtension;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RejectExtensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('task.approve');
    }

    public function rules(): array
    {
        return [
            'review_note' => ['required', 'string', 'max:1000'],
        ];
    }
}

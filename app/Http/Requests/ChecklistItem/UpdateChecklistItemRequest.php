<?php

/**
 * ==========================================================
 * MODUL       : UpdateChecklistItemRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi ubah TEKS item checklist (F-123) — task.manage ONLY
 *               (keputusan Boss LANGKAH 0: item = "syarat kerja", assignee cuma
 *               mencentang + boleh menambah baru, BUKAN mengubah teks item yang
 *               sudah ada — beda dari toggle is_done yang mixed access).
 * DIPANGGIL   : TaskChecklistItemController::update()
 * MEMANGGIL   : -
 * DATA MASUK  : text
 * DATA KELUAR : Data tervalidasi -> TaskChecklistItemController::update()
 * RISIKO      : SUMBER : authorize() SENGAJA hanya cek "sudah login" — gating
 *               task.manage DI CONTROLLER, pola sama StoreCommentRequest.
 * ==========================================================
 */

namespace App\Http\Requests\ChecklistItem;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:500'],
        ];
    }
}

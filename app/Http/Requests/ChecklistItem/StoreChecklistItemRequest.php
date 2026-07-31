<?php

/**
 * ==========================================================
 * MODUL       : StoreChecklistItemRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi tambah item checklist (F-123) — task.manage ATAU assignee
 *               task ini boleh menambah (keputusan Boss LANGKAH 0 v1.2 H5: item
 *               checklist = "syarat kerja" dari task.manage, TAPI assignee juga
 *               boleh menambah langkah tambahan yang ditemukan saat mengerjakan).
 * DIPANGGIL   : TaskChecklistItemController::store()
 * MEMANGGIL   : -
 * DATA MASUK  : text — task dari route model binding
 * DATA KELUAR : Data tervalidasi -> TaskChecklistItemController::store()
 * RISIKO      : SUMBER : authorize() SENGAJA hanya cek "sudah login" — gating
 *               task.manage/assignee (F-95) ada DI CONTROLLER, pola sama
 *               StoreCommentRequest (satu tempat saja yang menegakkan).
 * ==========================================================
 */

namespace App\Http\Requests\ChecklistItem;

use Illuminate\Foundation\Http\FormRequest;

class StoreChecklistItemRequest extends FormRequest
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

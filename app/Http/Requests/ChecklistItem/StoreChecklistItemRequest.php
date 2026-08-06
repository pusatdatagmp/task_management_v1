<?php

/**
 * ==========================================================
 * MODUL       : StoreChecklistItemRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi tambah item checklist (F-123) — task.manage SATU-SATUNYA
 *               yang boleh menambah (revisi Boss 2026-08-06, MENGGANTI keputusan
 *               LANGKAH 0 v1.2 H5 lama: assignee dulu boleh menambah langkah
 *               tambahan, sekarang dicabut — item checklist murni "syarat kerja"
 *               dari task.manage).
 * DIPANGGIL   : TaskChecklistItemController::store()
 * MEMANGGIL   : -
 * DATA MASUK  : text — task dari route model binding
 * DATA KELUAR : Data tervalidasi -> TaskChecklistItemController::store()
 * RISIKO      : SUMBER : authorize() SENGAJA hanya cek "sudah login" — gating
 *               task.manage (F-95) ada DI CONTROLLER, pola sama
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

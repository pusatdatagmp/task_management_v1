<?php

/**
 * ==========================================================
 * MODUL       : StoreAttachmentRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi upload attachment output (F-49) — assignee task ATAU admin
 *               (F-95), batas v0.5 (DM §3.12): 7 tipe file, maks 10 MB.
 * DIPANGGIL   : AttachmentController::store()
 * MEMANGGIL   : -
 * DATA MASUK  : File upload — task dari route model binding
 * DATA KELUAR : Data tervalidasi -> AttachmentController::store()
 * RISIKO      : SUMBER : authorize() SENGAJA hanya cek "sudah login" — gating
 *               assignee/admin (F-95) & freeze approve (F-104) ada di controller,
 *               pola sama UpdateTaskStatusRequest (satu tempat saja yang menegakkan).
 *               `mimes:` MEMBACA ISI FILE ASLI (Symfony Mime, bukan ekstensi klaim
 *               klien) — .exe di-rename .pdf akan GAGAL validasi ini (A2).
 * ==========================================================
 */

namespace App\Http\Requests\Attachment;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,docx,xlsx,zip', 'max:10240'],
        ];
    }
}

<?php

/**
 * ==========================================================
 * MODUL       : StoreAttachmentRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi upload attachment output (F-49) — assignee task ATAU admin
 *               (F-95), batas v0.5 (DM §3.12): 7 tipe file, maks 10 MB. Revisi
 *               2026-08-06 item 4: content_type menentukan MODE (file/link/text),
 *               field lain jadi WAJIB/KOSONG sesuai mode via required_if.
 * DIPANGGIL   : AttachmentController::store()
 * MEMANGGIL   : -
 * DATA MASUK  : File upload ATAU url ATAU body — task dari route model binding
 * DATA KELUAR : Data tervalidasi -> AttachmentController::store()
 * RISIKO      : SUMBER : authorize() SENGAJA hanya cek "sudah login" — gating
 *               assignee/admin (F-95) & freeze approve (F-104) ada di controller,
 *               pola sama UpdateTaskStatusRequest (satu tempat saja yang menegakkan).
 *               `mimes:` MEMBACA ISI FILE ASLI (Symfony Mime, bukan ekstensi klaim
 *               klien) — .exe di-rename .pdf akan GAGAL validasi ini (A2).
 *               `url` regex WAJIB http/https SAJA — Laravel 'url' rule SENDIRIAN
 *               tidak menolak scheme berbahaya (mis. `javascript:`), yang kalau
 *               lolos ke href bisa dieksekusi browser saat diklik (XSS via klik).
 * ==========================================================
 */

namespace App\Http\Requests\Attachment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'content_type' => ['required', Rule::in(['file', 'link', 'text'])],
            'file' => ['required_if:content_type,file', 'file', 'mimes:pdf,jpg,jpeg,png,docx,xlsx,zip', 'max:10240'],
            'url' => ['required_if:content_type,link', 'url', 'max:2048', 'regex:/^https?:\/\//i'],
            'body' => ['required_if:content_type,text', 'string', 'max:10000'],
        ];
    }
}

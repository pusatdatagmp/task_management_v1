<?php

/**
 * ==========================================================
 * MODUL       : StoreCommentRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi buat komentar baru (F-113) — project member ATAU admin
 *               (F-95), body wajib diisi.
 * DIPANGGIL   : CommentController::store()
 * MEMANGGIL   : -
 * DATA MASUK  : body — task dari route model binding
 * DATA KELUAR : Data tervalidasi -> CommentController::store()
 * RISIKO      : SUMBER : authorize() SENGAJA hanya cek "sudah login" — gating
 *               member/admin (F-95) ada di controller, pola sama
 *               StoreAttachmentRequest (satu tempat saja yang menegakkan).
 * ==========================================================
 */

namespace App\Http\Requests\Comment;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}

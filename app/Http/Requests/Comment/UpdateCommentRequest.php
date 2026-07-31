<?php

/**
 * ==========================================================
 * MODUL       : UpdateCommentRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi edit komentar (F-115) — HANYA penulis (dicek di controller,
 *               bukan di sini, pola sama StoreAttachmentRequest).
 * DIPANGGIL   : CommentController::update()
 * MEMANGGIL   : -
 * DATA MASUK  : body — comment dari route model binding
 * DATA KELUAR : Data tervalidasi -> CommentController::update()
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Http\Requests\Comment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
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

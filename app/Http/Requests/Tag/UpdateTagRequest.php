<?php

/**
 * ==========================================================
 * MODUL       : UpdateTagRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi ubah tag (permintaan Boss 2026-08-26) — nama unik per
 *               organisasi (F-5), MENGECUALIKAN baris yang sedang diedit sendiri.
 * DIPANGGIL   : TagController::update()
 * MEMANGGIL   : -
 * DATA MASUK  : Form tab "Tag" halaman Setelan
 * DATA KELUAR : Data tervalidasi -> TagController::update()
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Http\Requests\Tag;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('settings.manage'); // F-90, reuse (keputusan Boss)
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('tags', 'name')->where('organization_id', Auth::user()?->organization_id)->ignore($this->route('tag')),
            ],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Sudah ada tag dengan nama ini.',
        ];
    }
}

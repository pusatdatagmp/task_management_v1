<?php

/**
 * ==========================================================
 * MODUL       : StoreTagRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi tambah tag baru (permintaan Boss 2026-08-26) — nama unik
 *               per organisasi (F-5), warna hex (pola sama StoreTaskStatusRequest).
 * DIPANGGIL   : TagController::store()
 * MEMANGGIL   : -
 * DATA MASUK  : Form tab "Tag" halaman Setelan
 * DATA KELUAR : Data tervalidasi -> TagController::store()
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Http\Requests\Tag;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        // F-90 -- reuse settings.manage (keputusan Boss 2026-08-26), sama gate
        // dengan tab Branding/Tema/KPI lain di halaman Setelan.
        return (bool) Auth::user()?->can('settings.manage');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('tags', 'name')->where('organization_id', Auth::user()?->organization_id),
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

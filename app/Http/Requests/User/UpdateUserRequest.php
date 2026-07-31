<?php

/**
 * ==========================================================
 * MODUL       : UpdateUserRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi edit user/member (admin only, F-29). `password` OPSIONAL —
 *               kosong berarti admin tidak mengganti password user itu.
 * DIPANGGIL   : UserController::update()
 * MEMANGGIL   : -
 * DATA MASUK  : Form edit user
 * DATA KELUAR : Data tervalidasi -> UserController::update()
 * RISIKO      : `email` unique() WAJIB Rule::unique(...)->ignore($this->route('user'))
 *               — tanpa ignore, user menyimpan ulang emailnya sendiri tanpa ganti
 *               apa pun akan ditolak validasi (bentrok dengan baris miliknya sendiri).
 *               is_active SENGAJA TIDAK ADA di sini — nonaktifkan/aktifkan user
 *               adalah action terpisah (UserController::toggleActive()), bukan field
 *               form biasa yang bisa ke-toggle tanpa sengaja saat edit field lain.
 *               F-90 — `role_id` WAJIB milik organisasi yang sama (Rule::exists
 *               di-scope organization_id), BUKAN sekadar exists() polos — F-15.
 *               Edit HANYA assign role EKSISTING (mode 1 saja) — buat role baru
 *               lewat UI Role Management (Fase E1), bukan lewat form edit user.
 * ==========================================================
 */

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('user.manage');
    }

    public function rules(): array
    {
        $organizationId = Auth::user()?->organization_id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => ['nullable', Password::defaults(), 'confirmed'],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('organization_id', $organizationId)],
            'employment_type' => ['required', Rule::in(['internal', 'freelance'])],
            'daily_capacity_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

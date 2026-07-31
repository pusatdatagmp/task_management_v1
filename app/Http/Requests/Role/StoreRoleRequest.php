<?php

/**
 * ==========================================================
 * MODUL       : StoreRoleRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi bentuk payload buat role baru lewat UI Role Management
 *               (RBAC §E1) — BEDA dari onboarding user (OnboardUserRequest, 3-mode),
 *               ini SELALU mode "role baru dari kosong": nama + daftar permission.
 * DIPANGGIL   : RoleController::store()
 * MEMANGGIL   : UserService::createRole() (validasi bisnis C6 — duplikat/bentrok
 *               nama sistem/karakter aneh — ADA DI SANA, bukan di sini, supaya
 *               satu tempat dipakai onboarding DAN halaman ini)
 * DATA MASUK  : Form Role Baru
 * DATA KELUAR : Data tervalidasi -> RoleController::store()
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('user.manage'); // F-90
    }

    public function rules(): array
    {
        return [
            'role_name' => ['required', 'string', 'max:60'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ];
    }
}

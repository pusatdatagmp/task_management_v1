<?php

/**
 * ==========================================================
 * MODUL       : OnboardUserRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi onboarding user baru (RBAC §C4/C6, F-91 admin-only).
 *               MENGGANTIKAN StoreUserRequest lama — 'password' & 'role' enum
 *               sudah tidak ada (F-92: password acak by UserService, F-90: role
 *               dinamis lewat role_id/role baru, bukan enum tetap).
 * DIPANGGIL   : UserController::store()
 * MEMANGGIL   : UserService::resolveRole() (validasi LANJUTAN — nama role
 *               duplikat/bentrok sistem/karakter aneh, C6 — TIDAK diulang di sini
 *               karena butuh query DB per-organisasi yang lebih pas di service)
 * DATA MASUK  : Form onboarding (Fase E2) — role_mode ('existing'|'clone'|'new')
 *               + 3 bentuk payload role sesuai mode (F-93)
 * DATA KELUAR : Data tervalidasi -> UserController::store() -> UserService
 * RISIKO      : SUMBER : validasi di sini cuma BENTUK payload (shape/tipe), BUKAN
 *               isi bisnis (duplikat nama, dsb) — itu tetap tanggung jawab
 *               UserService (C6) supaya SATU tempat validasi bisnis, bukan dua
 *               yang bisa drift (pola sama F-72/F-76).
 * ==========================================================
 */

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OnboardUserRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'employment_type' => ['required', Rule::in(['internal', 'freelance'])],
            'daily_capacity_minutes' => ['nullable', 'integer', 'min:1'],

            // F-93 — diskriminator mode EKSPLISIT dari radio UI (F-74), bukan
            // ditebak dari field mana yang "terisi". lihat withValidator() untuk
            // kenapa menebak dari filled() gagal untuk field array.
            'role_mode' => ['required', Rule::in(['existing', 'clone', 'new'])],

            'role_id' => ['nullable', Rule::exists('roles', 'id')->where('organization_id', $organizationId)],
            'base_role_id' => ['nullable', Rule::exists('roles', 'id')->where('organization_id', $organizationId)],
            'new_role_name' => ['nullable', 'string', 'max:60'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
            'custom_permissions' => ['nullable', 'array'],
            'custom_permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ];
    }

    /**
     * BUSINESS RULE: F-74-style — TEPAT SATU dari 3 mode, ditentukan dari
     * `role_mode` (Rule::in di atas SUDAH memaksa persis satu dari 3 nilai —
     * itu sendiri jaminan "exactly-one", tidak perlu dihitung ulang di sini).
     *
     * WORKAROUND F-93: sebelumnya mode ditebak dari filled('base_role_id') ||
     * filled('custom_permissions') dkk. Itu BUG — Illuminate\Http\Request::filled()
     * TIDAK PERNAH menganggap array sebagai kosong (isEmptyString() eksplisit
     * skip array, lihat vendor Illuminate\Http\Concerns\InteractsWithInput).
     * Frontend (users/create.tsx transform()) SELALU mengirim custom_permissions:[]
     * dan permissions:[] di SETIAP mode (bukan di-null-kan seperti field
     * scalar) — jadi filled('custom_permissions') selalu true walau [],
     * membuat mode "existing" salah terhitung sebagai 2 mode sekaligus dan
     * ditolak. Ditemukan lewat browser nyata (F-75) — lolos di Pest karena
     * test lama tidak pernah mengirim key array kosong itu sama sekali.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mode = $this->input('role_mode');

            if ($mode === 'existing' && ! $this->filled('role_id')) {
                $validator->errors()->add('role_id', 'Pilih role yang sudah ada.');
            }

            if ($mode === 'clone' && (! $this->filled('base_role_id') || ! $this->filled('new_role_name'))) {
                $validator->errors()->add('role', 'Mode clone role butuh base_role_id DAN new_role_name.');
            }

            if ($mode === 'new' && ! $this->filled('new_role_name')) {
                $validator->errors()->add('new_role_name', 'Role baru butuh nama.');
            }
        });
    }
}

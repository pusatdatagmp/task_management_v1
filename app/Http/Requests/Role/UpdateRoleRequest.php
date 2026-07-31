<?php

/**
 * ==========================================================
 * MODUL       : UpdateRoleRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi edit role (RBAC §E1) — nama (role custom saja) +
 *               permission (role custom DAN sistem, dengan lantai minimum
 *               untuk role sistem, lihat RoleController::update()).
 * DIPANGGIL   : RoleController::update()
 * MEMANGGIL   : -
 * DATA MASUK  : Form edit role
 * DATA KELUAR : Data tervalidasi -> RoleController::update()
 * RISIKO      : role_name TIDAK divalidasi 'required' di sini — role SISTEM
 *               tidak mengirim field ini sama sekali (read-only di form, E1:
 *               "tidak bisa dihapus/rename"). RoleController yang memutuskan
 *               apakah rename benar-benar diterapkan (skip untuk is_system).
 * ==========================================================
 */

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('user.manage'); // F-90
    }

    public function rules(): array
    {
        $organizationId = Auth::user()?->organization_id;
        $roleId = $this->route('role')?->id;

        return [
            'role_name' => [
                'nullable', 'string', 'max:60',
                Rule::unique('roles', 'role_name')->where('organization_id', $organizationId)->ignore($roleId),
            ],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ];
    }
}

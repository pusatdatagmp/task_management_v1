<?php

/**
 * ==========================================================
 * MODUL       : StoreProjectRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi pembuatan project baru (admin only, F-29).
 * DIPANGGIL   : ProjectController::store()
 * MEMANGGIL   : Auth (scoping validasi owner_id/members ke organization_id sendiri)
 * DATA MASUK  : Form create project
 * DATA KELUAR : Data tervalidasi -> ProjectController::store()
 * RISIKO      : owner_id/members.* WAJIB di-scope ke organization_id user login —
 *               tanpa scope ini, admin bisa assign user dari organisasi lain sebagai
 *               owner/member (bug keamanan F-15, walau Rule::exists() query mentah
 *               tidak otomatis kena OrganizationScope Eloquent).
 * ==========================================================
 */

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('project.manage'); // F-90
    }

    public function rules(): array
    {
        $organizationId = Auth::user()?->organization_id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'owner_id' => ['required', Rule::exists('users', 'id')->where('organization_id', $organizationId)],
            'members' => ['nullable', 'array'],
            'members.*' => [Rule::exists('users', 'id')->where('organization_id', $organizationId)],
        ];
    }
}

<?php

/**
 * ==========================================================
 * MODUL       : UpdateProjectRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi edit project (admin only, F-29) — name/description/owner/members.
 *               TIDAK termasuk is_archived (itu action terpisah, ProjectController::archive()).
 * DIPANGGIL   : ProjectController::update()
 * MEMANGGIL   : Auth (scoping validasi owner_id/members ke organization_id sendiri)
 * DATA MASUK  : Form edit project
 * DATA KELUAR : Data tervalidasi -> ProjectController::update()
 * RISIKO      : Sama seperti StoreProjectRequest — owner_id/members.* wajib di-scope
 *               organization_id, bukan cuma exists() polos.
 * ==========================================================
 */

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
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

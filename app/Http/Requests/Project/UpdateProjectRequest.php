<?php

/**
 * ==========================================================
 * MODUL       : UpdateProjectRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi edit project (admin only, F-29) — name/description/owner/members.
 *               TIDAK termasuk is_archived (itu action terpisah, ProjectController::archive()).
 * DIPANGGIL   : ProjectController::update()
 * MEMANGGIL   : Auth (scoping validasi owner_ids/members ke organization_id sendiri)
 * DATA MASUK  : Form edit project
 * DATA KELUAR : Data tervalidasi -> ProjectController::update()
 * RISIKO      : Sama seperti StoreProjectRequest — owner_ids.* / members.* wajib
 *               di-scope organization_id, bukan cuma exists() polos.
 *               Revisi 2026-08-07 (permintaan Boss): owner BARU wajib
 *               permission `project.manage` (F-90) — TAPI owner LAMA (sudah
 *               jadi owner project ini) SELALU diloloskan walau permission-nya
 *               sudah dicabut belakangan. Tanpa pengecualian ini, admin yang
 *               cuma mau ubah nama/deskripsi project tiba-tiba gagal simpan
 *               gara-gara salah satu owner existing-nya kehilangan izin di
 *               waktu lain — kejutan yang tidak berhubungan dgn apa yang
 *               sedang diedit.
 *               2026-08-08 (keputusan Boss): owner_id tunggal -> owner_ids[]
 *               (min 1) — pengecualian "owner lama selalu lolos" sekarang
 *               dicek per-elemen terhadap SELURUH owner existing (bukan cuma 1).
 * ==========================================================
 */

namespace App\Http\Requests\Project;

use App\Models\Project;
use App\Models\User;
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
        /** @var Project $project */
        $project = $this->route('project');

        // ROBUST terhadap project_owners kosong (project dibuat langsung lewat
        // Project::create(['owner_id'=>..]), bukan lewat store() yang mengisi
        // pivot) -- owner_id (kolom lama) SELALU dianggap owner existing juga,
        // sama seperti ProjectController::currentOwnerIds().
        $currentOwnerIds = $project->owners()->pluck('users.id')->all();
        if ($project->owner_id && ! in_array($project->owner_id, $currentOwnerIds, true)) {
            $currentOwnerIds[] = $project->owner_id;
        }

        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'owner_ids' => ['required', 'array', 'min:1'],
            'owner_ids.*' => [
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
                function (string $attribute, mixed $value, \Closure $fail) use ($organizationId, $currentOwnerIds) {
                    if (in_array((int) $value, $currentOwnerIds, true)) {
                        return; // owner LAMA -- selalu diizinkan, lihat RISIKO header.
                    }

                    $eligible = User::where('id', $value)
                        ->where('organization_id', $organizationId)
                        ->whereHas('role.permissions', fn ($q) => $q->where('permission_name', 'project.manage'))
                        ->exists();

                    if (! $eligible) {
                        $fail('Owner harus user dengan izin project.manage (bukan member biasa).');
                    }
                },
            ],
            'members' => ['nullable', 'array'],
            'members.*' => [Rule::exists('users', 'id')->where('organization_id', $organizationId)],
        ];
    }
}

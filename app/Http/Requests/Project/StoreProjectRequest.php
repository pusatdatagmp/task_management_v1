<?php

/**
 * ==========================================================
 * MODUL       : StoreProjectRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi pembuatan project baru (admin only, F-29).
 * DIPANGGIL   : ProjectController::store()
 * MEMANGGIL   : Auth (scoping validasi owner_ids/members ke organization_id sendiri)
 * DATA MASUK  : Form create project
 * DATA KELUAR : Data tervalidasi -> ProjectController::store()
 * RISIKO      : owner_ids.* / members.* WAJIB di-scope ke organization_id user login —
 *               tanpa scope ini, admin bisa assign user dari organisasi lain sebagai
 *               owner/member (bug keamanan F-15, walau Rule::exists() query mentah
 *               tidak otomatis kena OrganizationScope Eloquent).
 *               Revisi 2026-08-07 (permintaan Boss): owner WAJIB user dengan
 *               permission `project.manage` (F-90 — per-permission, bukan
 *               hardcode nama role) — DITEGAKKAN DI SINI, bukan cuma checklist
 *               ProjectController::eligibleOwners() yang menyembunyikan member
 *               di UI. Tanpa validasi server ini, POST manual masih bisa
 *               menaruh member biasa sebagai owner/reviewer.
 *               2026-08-08 (keputusan Boss): owner_id tunggal -> owner_ids[]
 *               (min 1) — project boleh punya lebih dari 1 Owner/reviewer.
 * ==========================================================
 */

namespace App\Http\Requests\Project;

use App\Models\User;
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
            'owner_ids' => ['required', 'array', 'min:1'],
            'owner_ids.*' => [
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
                function (string $attribute, mixed $value, \Closure $fail) use ($organizationId) {
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

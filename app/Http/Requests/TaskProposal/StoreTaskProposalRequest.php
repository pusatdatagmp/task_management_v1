<?php

/**
 * ==========================================================
 * MODUL       : StoreTaskProposalRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : F-186 — validasi member mengajukan task baru untuk dirinya sendiri.
 *               Field SEPADAN StoreTaskRequest (form admin), TAPI tanpa assignees/
 *               parent_task_id — scope-out sengaja, assignee dikunci ke diri sendiri
 *               di server. `tags` DITAMBAH (permintaan Boss 2026-09-04, F-189 dicabut
 *               sebagian). `checklist_items` DITAMBAH (permintaan Boss 2026-09-04,
 *               pola IDENTIK StoreTaskRequest — F-189 dicabut lagi sebagian).
 * DIPANGGIL   : TaskProposalController::store()
 * MEMANGGIL   : -
 * DATA MASUK  : Form "Ajukan Tugas" (project dipilih dari dropdown, dibatasi
 *               project yang diikuti user login)
 * DATA KELUAR : Data tervalidasi -> TaskProposalController::store()
 * RISIKO      : SUMBER : project_id WAJIB salah satu project yang user login
 *               ikuti (Rule::exists ke project_user, F-186 "hanya diri sendiri,
 *               project sendiri") — bukan project mana pun se-organisasi.
 *               due_date WAJIB (F-31), sama seperti form admin.
 * ==========================================================
 */

namespace App\Http\Requests\TaskProposal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreTaskProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('task.proposeOwn'); // F-90/F-186
    }

    public function rules(): array
    {
        return [
            'project_id' => [
                'required',
                Rule::exists('project_user', 'project_id')->where('user_id', Auth::id()),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // F-186: sama pilihan dengan form admin (StoreTaskRequest) — keputusan
            // Boss 2026-09-04, bukan dikunci ke satu nilai.
            'task_type' => ['required', Rule::in(['tentative', 'project'])],
            'priority_quadrant' => ['nullable', Rule::in(['p1', 'p2', 'p3', 'p4'])],
            'estimated_minutes' => ['required', 'integer', 'min:1'],
            'points' => ['required', 'integer', 'min:0'],
            'due_date' => ['required', 'date'],
            // Permintaan Boss (2026-09-04): tag saat mengajukan, pola IDENTIK
            // StoreTaskRequest — tag WAJIB milik organisasi user login (cegah IDOR
            // pilih tag organisasi lain).
            'tags' => ['nullable', 'array'],
            'tags.*' => [Rule::exists('tags', 'id')->where('organization_id', Auth::user()?->organization_id)],
            // Permintaan Boss (2026-09-04): checklist ("subtask" ringan, F-123) bisa
            // diisi LANGSUNG saat mengajukan, pola IDENTIK StoreTaskRequest.
            'checklist_items' => ['sometimes', 'array'],
            'checklist_items.*' => ['string', 'max:500'],
        ];
    }
}

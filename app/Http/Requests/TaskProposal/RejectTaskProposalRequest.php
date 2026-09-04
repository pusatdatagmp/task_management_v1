<?php

/**
 * ==========================================================
 * MODUL       : RejectTaskProposalRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : F-186 — validasi admin reject pengajuan task. review_note WAJIB —
 *               member berhak tahu alasan penolakan (pola sama RejectExtensionRequest).
 * DIPANGGIL   : TaskProposalController::reject()
 * MEMANGGIL   : -
 * DATA MASUK  : Form reject (proposal dari route model binding)
 * DATA KELUAR : Data tervalidasi -> TaskProposalController::reject()
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Http\Requests\TaskProposal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RejectTaskProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('task.approve'); // F-90/F-186 (reuse, bukan permission baru)
    }

    public function rules(): array
    {
        return [
            'review_note' => ['required', 'string', 'max:1000'],
        ];
    }
}

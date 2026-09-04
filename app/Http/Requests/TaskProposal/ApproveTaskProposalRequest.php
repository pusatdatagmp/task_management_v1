<?php

/**
 * ==========================================================
 * MODUL       : ApproveTaskProposalRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : F-186 — validasi admin approve pengajuan task. due_date/
 *               estimated_minutes/points WAJIB DIISI ULANG (bukan re-post nilai
 *               member apa adanya) — keputusan Boss 2026-09-04: admin WAJIB review
 *               angka ini sebelum task aktif, cegah member menulis estimasi longgar
 *               untuk aman dari penalti KPI (Task::isOnTime() pakai estimated_minutes
 *               & due_date langsung, F-109).
 * DIPANGGIL   : TaskProposalController::approve()
 * MEMANGGIL   : -
 * DATA MASUK  : Form approve (proposal dari route model binding)
 * DATA KELUAR : Data tervalidasi -> TaskProposalController::approve()
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Http\Requests\TaskProposal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ApproveTaskProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('task.approve'); // F-90/F-186 (reuse, bukan permission baru)
    }

    public function rules(): array
    {
        return [
            'due_date' => ['required', 'date'],
            'estimated_minutes' => ['required', 'integer', 'min:1'],
            'points' => ['required', 'integer', 'min:0'],
        ];
    }
}

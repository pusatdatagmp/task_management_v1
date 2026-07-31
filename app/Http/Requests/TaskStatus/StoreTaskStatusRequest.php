<?php

/**
 * ==========================================================
 * MODUL       : StoreTaskStatusRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi tambah status custom baru per project (admin only).
 *               HANYA identitas (nama, warna) — 3 flag TIDAK diminta di sini lagi.
 * DIPANGGIL   : TaskStatusController::store()
 * MEMANGGIL   : -
 * DATA MASUK  : Form tambah status (name, color) — project dari route model binding
 * DATA KELUAR : Data tervalidasi -> TaskStatusController::store()
 * RISIKO      : SUMBER : F-74/Hari-5 — status baru SELALU dibuat dengan ketiga flag
 *               false (lihat TaskStatusController::store()). Admin mengatur flag
 *               (siapa jadi "selesai"/"review"/"sedang dikerjakan") lewat form radio
 *               di halaman index SETELAHNYA — bukan saat create. Ini disengaja:
 *               constraint "tepat 1 completed" cuma bisa diedit aman kalau SEMUA
 *               status project terlihat sekaligus (lihat UpdateTaskStatusFlagsRequest).
 * ==========================================================
 */

namespace App\Http\Requests\TaskStatus;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('status.manage'); // F-90
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}

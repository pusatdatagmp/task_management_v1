<?php

/**
 * ==========================================================
 * MODUL       : QuickEditWorkScheduleRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi "Edit" di kartu Jam Kerja Saat Ini (audit Boss 2026-08-12,
 *               F-169) — SAMA persis StoreWorkScheduleRequest (F-42 kapasitas<=
 *               jendela) TAPI TANPA field effective_from: tanggal SELALU dipaksa
 *               HARI INI di WorkScheduleController::quickEdit(), client tidak
 *               boleh kirim tanggal lain sama sekali (nol celah backdate/future-date
 *               lewat jalur ini, F-70 tetap ditegakkan by design bukan validasi).
 * DIPANGGIL   : WorkScheduleController::quickEdit()
 * MEMANGGIL   : Auth
 * DATA MASUK  : Form kartu Jam Kerja Saat Ini (admin only) — hari/jam/kapasitas saja
 * DATA KELUAR : Data tervalidasi -> quickEdit() -> WorkSchedule::create() (F-40 INSERT)
 * RISIKO      : -
 * ==========================================================
 */

namespace App\Http\Requests\WorkSchedule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class QuickEditWorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // F-90 — penegakan sebenarnya di middleware route (can:workschedule.manage,
        // routes/admin.php). Ini cuma lapis kedua.
        return (bool) Auth::user()?->can('workschedule.manage');
    }

    public function rules(): array
    {
        return [
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'daily_capacity_minutes' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'days_of_week.min' => 'Pilih minimal 1 hari kerja.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
        ];
    }

    /**
     * BUSINESS RULE: F-42 — IDENTIK StoreWorkScheduleRequest, lihat komentar di sana.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $start = $this->input('start_time');
            $end = $this->input('end_time');
            $capacity = $this->input('daily_capacity_minutes');

            if (! $start || ! $end || ! is_numeric($capacity)) {
                return;
            }

            [$startHour, $startMinute] = array_pad(explode(':', $start), 2, 0);
            [$endHour, $endMinute] = array_pad(explode(':', $end), 2, 0);
            $windowMinutes = (((int) $endHour * 60) + (int) $endMinute) - (((int) $startHour * 60) + (int) $startMinute);

            if ($windowMinutes > 0 && $capacity > $windowMinutes) {
                $validator->errors()->add(
                    'daily_capacity_minutes',
                    "Kapasitas ({$capacity} menit) tidak boleh lebih besar dari panjang jendela kerja ({$windowMinutes} menit)."
                );
            }
        });
    }
}

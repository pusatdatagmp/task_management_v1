<?php

/**
 * ==========================================================
 * MODUL       : UpdateWorkScheduleRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi EDIT versi Jam Kerja (permintaan Boss 2026-08-10, audit
 *               F-40) -- rule IDENTIK StoreWorkScheduleRequest (F-70 anti-backdate,
 *               F-42 kapasitas<=jendela), plus unique effective_from IGNORE baris
 *               ini sendiri (bukan bentrok dengan dirinya). Guard "cuma versi
 *               FUTURE yang boleh diedit" ada di WorkScheduleController::update()
 *               (butuh instance $workSchedule dari route model binding, bukan
 *               tugas FormRequest ini -- pola sama UpdateProjectRequest dkk).
 * DIPANGGIL   : WorkScheduleController::update()
 * MEMANGGIL   : WorkSchedule (unique check, ignore self)
 * DATA MASUK  : Form edit versi Jam Kerja (admin only)
 * DATA KELUAR : Data tervalidasi -> WorkScheduleController::update() -> UPDATE baris (BUKAN insert)
 * RISIKO      : SUMBER : F-70 tetap ditegakkan DI SINI juga -- edit versi future
 *               TIDAK BOLEH dipakai buat menggeser effective_from-nya jadi tanggal
 *               lampau (celah sama seperti store() kalau validasi ini dilewatkan).
 * ==========================================================
 */

namespace App\Http\Requests\WorkSchedule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkScheduleRequest extends FormRequest
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
            'effective_from' => [
                'required',
                'date',
                'after_or_equal:today',
                Rule::unique('work_schedules', 'effective_from')
                    ->where('organization_id', Auth::user()?->organization_id)
                    ->ignore($this->route('workSchedule')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'days_of_week.min' => 'Pilih minimal 1 hari kerja.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
            'effective_from.after_or_equal' => 'Tanggal berlaku tidak boleh mundur ke masa lalu (F-70) — pilih hari ini atau setelahnya.',
            'effective_from.unique' => 'Sudah ada versi jam kerja yang mulai berlaku di tanggal ini.',
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

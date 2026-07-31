<?php

/**
 * ==========================================================
 * MODUL       : StoreWorkScheduleRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi versi baru jam kerja (F-40). Menegakkan F-70 (anti-backdate)
 *               dan F-42 (kapasitas boleh < panjang jendela, tidak boleh >).
 * DIPANGGIL   : WorkScheduleController::store()
 * MEMANGGIL   : WorkSchedule (unique check), Auth
 * DATA MASUK  : Form Pengaturan > Jam Kerja (admin only)
 * DATA KELUAR : Data tervalidasi -> WorkScheduleController::store() -> INSERT baris baru
 * RISIKO      : SUMBER : F-70 — kalau validasi anti-backdate ini bolong, admin bisa
 *               menulis ulang jendela kerja masa lalu dan diam-diam mengubah realisasi
 *               task yang sedang berjalan (belum di-approve, F-39 belum membekukannya).
 * ==========================================================
 */

namespace App\Http\Requests\WorkSchedule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkScheduleRequest extends FormRequest
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
            // F-70: JANGAN PERNAH izinkan tanggal lampau — lihat komentar di withValidator().
            'effective_from' => [
                'required',
                'date',
                'after_or_equal:today',
                Rule::unique('work_schedules', 'effective_from')
                    ->where('organization_id', Auth::user()?->organization_id),
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
     * BUSINESS RULE: F-42 — daily_capacity_minutes BOLEH lebih kecil dari panjang
     * jendela (start_time..end_time), itu jam istirahat. TAPI tidak boleh lebih besar
     * — kapasitas produktif tidak mungkin melebihi jendela yang tersedia untuk bekerja.
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

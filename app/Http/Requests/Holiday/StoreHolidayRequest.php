<?php

/**
 * ==========================================================
 * MODUL       : StoreHolidayRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi tambah hari libur (F-43) — tanggal unik per organization (F-5).
 * DIPANGGIL   : HolidayController::store()
 * MEMANGGIL   : Holiday (unique check), Auth
 * DATA MASUK  : Form Pengaturan > Hari Libur (admin only)
 * DATA KELUAR : Data tervalidasi -> HolidayController::store() -> INSERT
 * RISIKO      : Kalau unique constraint ini bolong, 2 baris holiday tanggal sama bisa
 *               masuk — tidak fatal (BusinessHoursCalculator index per tanggal, hasil
 *               tetap 0 menit di hari itu), tapi UI riwayat jadi membingungkan (F-51 spirit).
 * ==========================================================
 */

namespace App\Http\Requests\Holiday;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreHolidayRequest extends FormRequest
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
            'date' => [
                'required',
                'date',
                Rule::unique('holidays', 'date')->where('organization_id', Auth::user()?->organization_id),
            ],
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.unique' => 'Sudah ada hari libur di tanggal ini.',
        ];
    }
}

<?php

/**
 * ==========================================================
 * MODUL       : UpdateHolidayRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi ubah hari libur (F-43) — tanggal unik per organization (F-5),
 *               MENGECUALIKAN baris yang sedang diedit sendiri.
 * DIPANGGIL   : HolidayController::update()
 * MEMANGGIL   : Holiday (unique check), Auth
 * DATA MASUK  : Form Pengaturan > Hari Libur (admin only)
 * DATA KELUAR : Data tervalidasi -> HolidayController::update()
 * RISIKO      : Sama seperti StoreHolidayRequest, ditambah ->ignore() supaya submit
 *               ulang tanggal yang sama (tanpa ubah) tidak ditolak sebagai duplikat.
 * ==========================================================
 */

namespace App\Http\Requests\Holiday;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) Auth::user()?->can('workschedule.manage');
    }

    public function rules(): array
    {
        return [
            'date' => [
                'required',
                'date',
                Rule::unique('holidays', 'date')
                    ->where('organization_id', Auth::user()?->organization_id)
                    ->ignore($this->route('holiday')),
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

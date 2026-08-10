<?php

/**
 * ==========================================================
 * MODUL       : UpdateKpiRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi form tab KPI halaman Setelan (F-166, v1.4 KPI-2, pola
 *               SAMA UpdateThemeRequest/UpdateBrandingRequest DS-2/DS-3). Admin
 *               override poin default SimpleTimelinessStrategy (ontime=5/telat=3/
 *               notdone=0, blueprint §14.2) + master toggle kpi_enabled.
 * DIPANGGIL   : SettingsController::updateKpi()
 * MEMANGGIL   : -
 * DATA MASUK  : Form Setelan tab KPI (kpi_enabled bool, kpi_points_ontime/late/
 *               notdone int >= 0)
 * DATA KELUAR : Data tervalidasi -> SettingsController::updateKpi()
 * RISIKO      : `kpi_strategy` SENGAJA TIDAK ADA di sini -- cuma 1 strategy
 *               terdaftar di KpiStrategyRegistry (simple_timeliness), selector
 *               1-opsi tidak berguna sekarang. Tambah field ini saat strategy
 *               ke-2 dibangun (v1.5), bukan sebelumnya.
 * ==========================================================
 */

namespace App\Http\Requests\Branding;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKpiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'kpi_enabled' => ['required', 'boolean'],
            'kpi_points_ontime' => ['required', 'integer', 'min:0', 'max:32767'],
            'kpi_points_late' => ['required', 'integer', 'min:0', 'max:32767'],
            'kpi_points_notdone' => ['required', 'integer', 'min:0', 'max:32767'],
        ];
    }
}

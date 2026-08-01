<?php

/**
 * ==========================================================
 * MODUL       : UpdateBrandingRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi form tab Branding halaman Setelan (F-142, v1.2 DS-2).
 *               `logo` OPSIONAL (kosong = logo lama dipertahankan, TIDAK dihapus).
 * DIPANGGIL   : SettingsController::updateBranding()
 * MEMANGGIL   : -
 * DATA MASUK  : Form Setelan tab Branding (company_name, address, wa_number,
 *               facebook_url/instagram_url/linkedin_url, logo file opsional)
 * DATA KELUAR : Data tervalidasi -> SettingsController::updateBranding()
 * RISIKO      : SUMBER : `mimes:` (BUKAN cuma `image`) — `image` bawaan Laravel
 *               tidak accept svg, dan svg SENGAJA DIKECUALIKAN dari daftar (XSS —
 *               file SVG bisa membawa <script>, beda dari raster image biasa).
 *               `wa_number` regex angka saja (opsional +) — dipakai bangun link
 *               wa.me/{nomor} di frontend, format bebas-karakter bisa membentuk
 *               URL rusak.
 * ==========================================================
 */

namespace App\Http\Requests\Branding;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'wa_number' => ['nullable', 'regex:/^\+?[0-9]{8,20}$/'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            // A2/A3 pola F-104/105: mimes: baca ISI FILE (Symfony Mime), bukan
            // ekstensi klaim klien. max 2048 KB (2 MB, lebih kecil dari attachment
            // 10 MB -- logo cuma butuh resolusi kecil, bukan dokumen kerja).
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}

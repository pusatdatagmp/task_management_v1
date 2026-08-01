<?php

/**
 * ==========================================================
 * MODUL       : UpdateThemeRequest
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Validasi form tab Tema halaman Setelan (F-143, v1.2 DS-3).
 *               Token INTI yang boleh diedit = 8 yang benar-benar dipakai
 *               komponen (F-144, lihat app.css) -- sidebar_bg (BARU, dipisah dari
 *               ink DS-3), ink, ink2, paper, card, amber, tx, tx2. Token
 *               dekoratif (accent/emerald/rose/tx3, belum dipakai komponen mana
 *               pun) SENGAJA TIDAK ada di sini -- keputusan Boss LANGKAH 0.
 * DIPANGGIL   : SettingsController::updateTheme()
 * MEMANGGIL   : -
 * DATA MASUK  : Form tab Tema (tokens{...} hex per key, gradient{enabled,from,to,direction})
 * DATA KELUAR : Data tervalidasi -> SettingsController::updateTheme()
 * RISIKO      : SUMBER : whitelist KETAT (hex 6-digit persis keluaran
 *               <input type=color>, direction enum) -- nilai ini nantinya
 *               dipakai bangun string `linear-gradient(...)` yang di-inject ke
 *               CSS custom property lewat setProperty(). Longgarkan validasi ini
 *               = celah CSS/style injection kalau suatu saat dirender lewat jalur
 *               lain (mis. SSR <style> tag), bukan cuma DOM API yang otomatis aman.
 * ==========================================================
 */

namespace App\Http\Requests\Branding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateThemeRequest extends FormRequest
{
    /**
     * KONTRAK: daftar token yang boleh diisi Boss -- SATU sumber dipakai rules()
     * DI SINI dan SettingsController::updateTheme() (susun ulang array final),
     * supaya key yang lolos validasi sama persis dengan yang disimpan (F-90-style
     * satu sumber, bukan whitelist ganda yang bisa drift).
     *
     * @return list<string>
     */
    public static function tokenKeys(): array
    {
        return ['sidebar_bg', 'ink', 'ink2', 'paper', 'card', 'amber', 'tx', 'tx2'];
    }

    public static function gradientDirections(): array
    {
        return ['to right', 'to bottom', 'to bottom right'];
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $hex = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];

        $rules = [
            'tokens' => ['nullable', 'array'],
            'gradient' => ['nullable', 'array'],
            'gradient.enabled' => ['nullable', 'boolean'],
            'gradient.from' => ['required_if:gradient.enabled,true', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'gradient.to' => ['required_if:gradient.enabled,true', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'gradient.direction' => ['nullable', Rule::in(self::gradientDirections())],
        ];

        foreach (self::tokenKeys() as $key) {
            $rules["tokens.{$key}"] = $hex;
        }

        return $rules;
    }
}

<?php

/**
 * ==========================================================
 * MODUL       : SettingsController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Halaman "Setelan" org-level — tab Branding (F-142, v1.2 DS-2) +
 *               tab Tema (F-143, v1.2 DS-3, token+gradasi). SATU controller
 *               untuk shell halaman + tiap tab (F-144 -- editor MENGUBAH NILAI
 *               TOKEN, komponen mewarisi lewat CSS var, controller ini TIDAK
 *               PERNAH menyentuh warna per-komponen).
 * DIPANGGIL   : routes/admin.php (can:settings.manage)
 * MEMANGGIL   : Organization (branding/tema SELALU milik Auth::user()->organization,
 *               TIDAK PERNAH dari route model binding — F-5, cegah IDOR org lain)
 * DATA MASUK  : Form Branding (UpdateBrandingRequest), Form Tema (UpdateThemeRequest)
 *               -- tokens{sidebar_bg,ink,ink2,paper,card,amber,tx,tx2} + gradient
 * DATA KELUAR : Inertia page 'org-settings/index', update organizations.{*, theme_config}
 * RISIKO      : SUMBER : organization SELALU diambil dari user login
 *               ($request->user()->organization), BUKAN {organization} di URL —
 *               kalau nanti ada yang "memudahkan" jadi route model binding,
 *               admin org A bisa timpa branding/tema org B (IDOR, F-5 bocor).
 *               Logo lama DIHAPUS fisik saat diganti (beda dari Attachment F-104/105
 *               yang append-only/riwayat) — branding cuma 1 nilai aktif, bukan audit trail.
 *               updateTheme() HANYA menyimpan key yang lolos UpdateThemeRequest::
 *               tokenKeys() -- payload liar tak pernah ikut masuk theme_config
 *               walau validasi FormRequest 'array' longgar soal key tambahan.
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\Branding\UpdateBrandingRequest;
use App\Http\Requests\Branding\UpdateThemeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $organization = $request->user()->organization;

        return Inertia::render('org-settings/index', [
            'branding' => [
                ...$organization->only(['company_name', 'address', 'wa_number', 'facebook_url', 'instagram_url', 'linkedin_url']),
                'logo_url' => $organization->logoUrl(),
            ],
            'theme' => $organization->theme_config,
        ]);
    }

    public function updateBranding(UpdateBrandingRequest $request): RedirectResponse
    {
        $organization = $request->user()->organization;

        if ($request->hasFile('logo')) {
            // SUMBER: branding cuma 1 logo aktif (bukan riwayat attachment F-104/105)
            // -- file lama WAJIB dihapus saat diganti, atau storage/app/public
            // menumpuk file yatim selamanya tiap kali admin ganti logo.
            if ($organization->logo_path) {
                Storage::disk('public')->delete($organization->logo_path);
            }

            // A3 (pola F-104/105): nama fisik UUID + ekstensi dari ISI FILE nyata,
            // bukan nama/ekstensi klaim klien -- cegah path traversal/collision.
            $file = $request->file('logo');
            $storedName = Str::uuid()->toString().'.'.$file->extension();
            $organization->logo_path = $file->storeAs('branding', $storedName, 'public');
        }

        $organization->fill($request->safe()->except('logo'));
        $organization->save();

        return back();
    }

    /**
     * BUSINESS RULE: F-143/F-144 -- editor MENGUBAH NILAI TOKEN, bukan warna
     * per-komponen. Payload disusun ULANG dari whitelist UpdateThemeRequest::
     * tokenKeys() (bukan $request->validated() mentah) -- kalau suatu saat
     * validasi 'array' longgar meloloskan key tak dikenal, key liar itu TETAP
     * tak pernah masuk theme_config tersimpan.
     */
    public function updateTheme(UpdateThemeRequest $request): RedirectResponse
    {
        $organization = $request->user()->organization;

        $tokens = collect(UpdateThemeRequest::tokenKeys())
            ->mapWithKeys(fn (string $key) => [$key => $request->validated("tokens.{$key}")])
            ->filter()
            ->all();

        $gradient = $request->boolean('gradient.enabled')
            ? [
                'enabled' => true,
                'from' => $request->validated('gradient.from'),
                'to' => $request->validated('gradient.to'),
                'direction' => $request->validated('gradient.direction') ?? 'to right',
            ]
            : null;

        $organization->theme_config = [
            'tokens' => $tokens,
            'gradient' => $gradient,
        ];
        $organization->save();

        return back();
    }
}

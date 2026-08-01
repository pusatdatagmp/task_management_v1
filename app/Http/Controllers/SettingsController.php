<?php

/**
 * ==========================================================
 * MODUL       : SettingsController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Halaman "Setelan" org-level (F-142, v1.2 DS-2) — tab Branding
 *               sekarang, slot tab Tema (DS-3) menyusul. SATU controller untuk
 *               shell halaman + tiap tab, supaya DS-3 nanti tinggal tambah method
 *               (updateTheme()) di sini, BUKAN controller/permission baru.
 * DIPANGGIL   : routes/admin.php (can:settings.manage)
 * MEMANGGIL   : Organization (branding SELALU milik Auth::user()->organization,
 *               TIDAK PERNAH dari route model binding — F-5, cegah IDOR org lain)
 * DATA MASUK  : Form Branding (UpdateBrandingRequest) — company_name, address,
 *               wa_number, sosmed, logo file opsional
 * DATA KELUAR : Inertia page 'org-settings/index', update organizations.*
 * RISIKO      : SUMBER : organization SELALU diambil dari user login
 *               ($request->user()->organization), BUKAN {organization} di URL —
 *               kalau nanti ada yang "memudahkan" jadi route model binding,
 *               admin org A bisa timpa branding org B (IDOR, F-5 bocor).
 *               Logo lama DIHAPUS fisik saat diganti (beda dari Attachment F-104/105
 *               yang append-only/riwayat) — branding cuma 1 nilai aktif, bukan audit trail.
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\Branding\UpdateBrandingRequest;
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
}

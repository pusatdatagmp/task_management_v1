<?php

/**
 * ==========================================================
 * MODUL       : TagController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : CRUD katalog Tag per-organisasi (permintaan Boss 2026-08-26) --
 *               dikelola dari tab "Tag" di halaman Setelan (SettingsController::
 *               edit() yang mengirim datanya, controller ini cuma store/update/
 *               destroy). Permission REUSE settings.manage (keputusan Boss),
 *               bukan permission baru -- pola sama Branding/Tema/KPI.
 * DIPANGGIL   : routes/admin.php (can:settings.manage)
 * MEMANGGIL   : Tag (BelongsToOrganization -- organization_id SELALU dari
 *               Auth::user(), TIDAK PERNAH dari input, F-5 cegah IDOR)
 * DATA MASUK  : Form tambah/ubah/hapus tag
 * DATA KELUAR : back() -> tab Tag di halaman Setelan render ulang daftar terbaru
 * RISIKO      : SUMBER : destroy() HAPUS PERMANEN (bukan soft-delete -- Tag bukan
 *               tabel KPI, F-16 cuma wajib untuk users/projects/tasks, pola sama
 *               Holiday::destroy()) DAN otomatis melepas tag ini dari SEMUA task
 *               yang memakainya lewat cascadeOnDelete() migration task_tag
 *               (keputusan Boss 2026-08-26 -- BUKAN ditolak seperti TaskStatus).
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\Tag\StoreTagRequest;
use App\Http\Requests\Tag\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class TagController extends Controller
{
    public function store(StoreTagRequest $request): RedirectResponse
    {
        Tag::create([
            ...$request->validated(),
            'organization_id' => Auth::user()->organization_id,
        ]);

        return back();
    }

    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->validated());

        return back();
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return back();
    }
}

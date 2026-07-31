<?php

/**
 * ==========================================================
 * MODUL       : WorkScheduleController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Pengaturan Jam Kerja (F-40, versioned). HANYA index + store — TIDAK
 *               ADA edit/delete (B6, versi lama = arsip permanen).
 * DIPANGGIL   : routes/admin.php
 * MEMANGGIL   : WorkSchedule (INSERT baru, tidak pernah UPDATE)
 * DATA MASUK  : Form tambah versi jam kerja, admin only
 * DATA KELUAR : Inertia page 'work-schedules/index' dengan riwayat versi
 * RISIKO      : SUMBER : F-40 — JANGAN PERNAH panggil ->update() di controller ini.
 *               Ubah pengaturan = create() baris baru. Kalau ada kebutuhan "edit",
 *               itu tandanya harus jadi versi baru, bukan menambah method update().
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\WorkSchedule\StoreWorkScheduleRequest;
use App\Models\WorkSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkScheduleController extends Controller
{
    /**
     * KONTRAK: daftar RIWAYAT semua versi (F-40), urut effective_from desc, dengan
     * penanda versi mana yang aktif sekarang (WorkSchedule::active(), F-66).
     */
    public function index(Request $request): Response
    {
        // SUMBER: query otomatis ter-scope organization_id via OrganizationScope
        // (F-15) — tidak perlu where() manual di sini.
        $schedules = WorkSchedule::with('creator')->orderByDesc('effective_from')->get();

        $activeId = WorkSchedule::active($request->user()->organization_id)?->id;

        return Inertia::render('work-schedules/index', [
            'schedules' => $schedules,
            'activeId' => $activeId,
        ]);
    }

    /**
     * F-40: SIMPAN = INSERT baris baru. organization_id & created_by terisi dari
     * user login (BelongsToOrganization trait untuk organization_id).
     */
    public function store(StoreWorkScheduleRequest $request): RedirectResponse
    {
        WorkSchedule::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return to_route('work-schedules.index');
    }
}

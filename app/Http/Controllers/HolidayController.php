<?php

/**
 * ==========================================================
 * MODUL       : HolidayController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Pengaturan > Hari Libur (F-43) — kalender libur organisasi yang
 *               dipakai BusinessHoursCalculator untuk men-skip hari libur dari
 *               realisasi kerja (sama seperti akhir pekan).
 * DIPANGGIL   : routes/admin.php
 * MEMANGGIL   : Holiday
 * DATA MASUK  : Form tambah/ubah/hapus hari libur, admin only (permission workschedule.manage)
 * DATA KELUAR : Inertia page 'holidays/index' — daftar urut tanggal
 * RISIKO      : SUMBER : F-43/F-39 — ubah/hapus holiday MENGUBAH rumus realisasi
 *               untuk task yang BELUM di-approve (belum frozen). Task yang actual_minutes-nya
 *               SUDAH frozen (F-39) tidak terpengaruh — angka itu sudah beku permanen,
 *               tidak dihitung ulang oleh apa pun termasuk perubahan kalender ini.
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\Holiday\StoreHolidayRequest;
use App\Http\Requests\Holiday\UpdateHolidayRequest;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function index(): Response
    {
        // SUMBER: query otomatis ter-scope organization_id via OrganizationScope (F-15).
        $holidays = Holiday::orderBy('date')->get();

        return Inertia::render('holidays/index', [
            'holidays' => $holidays,
        ]);
    }

    public function store(StoreHolidayRequest $request): RedirectResponse
    {
        Holiday::create($request->validated());

        return to_route('holidays.index');
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $holiday->update($request->validated());

        return to_route('holidays.index');
    }

    /**
     * BUSINESS RULE: Holiday BUKAN tabel KPI (F-16 soft-delete cuma wajib untuk
     * users/projects/tasks) — hapus permanen di sini sah, bukan pelanggaran F-16.
     */
    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return to_route('holidays.index');
    }
}

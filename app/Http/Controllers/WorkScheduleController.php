<?php

/**
 * ==========================================================
 * MODUL       : WorkScheduleController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Pengaturan Jam Kerja (F-40, versioned). index + store (versi baru,
 *               INSERT) + update/archive TERBATAS versi FUTURE (permintaan Boss
 *               2026-08-10, audit F-40 -- lihat RISIKO) + activateNow() (Boss
 *               mau "pilih mana yang aktif" TANPA urus tanggal -- diselesaikan
 *               TETAP dalam batas F-40: SALIN ke baris baru effective_from hari
 *               ini, bukan toggle flag "aktif" lintas tanggal).
 * DIPANGGIL   : routes/admin.php
 * MEMANGGIL   : WorkSchedule (INSERT untuk versi baru; UPDATE HANYA utk versi
 *               yang belum pernah aktif)
 * DATA MASUK  : Form tambah/edit versi jam kerja, admin only
 * DATA KELUAR : Inertia page 'work-schedules/index' dengan riwayat versi
 * RISIKO      : SUMBER : F-40 — versi yang SUDAH PERNAH aktif (effective_from <=
 *               hari ini, ikut menentukan actual_minutes/KPI task yang SUDAH
 *               dibekukan) TETAP TERKUNCI PERMANEN, TIDAK BOLEH di-update/arsip
 *               lewat jalur mana pun. Ubah pengaturan versi itu = create() baris
 *               baru (F-40 asli). update()/archive() DI SINI HANYA menerima versi
 *               dengan effective_from > HARI INI (belum pernah dipakai hitung
 *               apa pun) — guard $isFuture() di KEDUA method, bukan di FormRequest
 *               (FormRequest tidak punya akses ke instance $workSchedule yang lama).
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Http\Requests\WorkSchedule\StoreWorkScheduleRequest;
use App\Http\Requests\WorkSchedule\UpdateWorkScheduleRequest;
use App\Models\WorkSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

    /**
     * KONTRAK: EDIT versi Jam Kerja (permintaan Boss 2026-08-10, audit F-40).
     * GUARD WAJIB: cuma versi effective_from > HARI INI (belum pernah aktif,
     * nol dampak actual_minutes/KPI yang sudah dibekukan) — versi yang SUDAH
     * PERNAH aktif TETAP terkunci permanen, F-40 asli tidak dilonggarkan
     * sedikit pun untuk versi itu. isFuture() (BUKAN isToday()/>=) -- versi
     * yang effective_from-nya PERSIS hari ini SUDAH dianggap aktif/dipakai
     * (WorkSchedule::active() pakai <=), jadi HARUS ikut terkunci, bukan boleh diedit.
     */
    public function update(UpdateWorkScheduleRequest $request, WorkSchedule $workSchedule): RedirectResponse
    {
        if (! $workSchedule->effective_from->isFuture()) {
            throw ValidationException::withMessages([
                'effective_from' => 'Versi ini sudah pernah/sedang aktif — tidak bisa diedit (F-40). Buat versi baru untuk mengubah pengaturan mulai tanggal tertentu.',
            ]);
        }

        $workSchedule->update($request->validated());

        return to_route('work-schedules.index');
    }

    /**
     * KONTRAK: ARSIP MANUAL versi Jam Kerja (permintaan Boss 2026-08-10, audit
     * F-40) -- pembatalan versi FUTURE yang salah/tidak jadi dipakai, TANPA
     * hard delete (pola sama Project::is_archived, F-16 semangat). GUARD SAMA
     * PERSIS update() -- lihat komentar di sana.
     */
    public function archive(WorkSchedule $workSchedule): RedirectResponse
    {
        if (! $workSchedule->effective_from->isFuture()) {
            throw ValidationException::withMessages([
                'effective_from' => 'Versi ini sudah pernah/sedang aktif — tidak bisa diarsipkan (F-40).',
            ]);
        }

        $workSchedule->update(['is_archived' => true]);

        return to_route('work-schedules.index');
    }

    /**
     * KONTRAK: "Jadikan Aktif Sekarang" (permintaan Boss 2026-08-10 — mau bisa
     * PILIH versi mana yang aktif, tanpa mengurus tanggal/arsip). BUKAN toggle
     * flag (itu akan melanggar F-40 -- lihat audit sebelumnya: pilih-manual-
     * tanpa-tanggal bisa menulis ulang KPI task yang sedang berjalan). SEBAGAI
     * GANTINYA: SALIN isi $workSchedule (baris manapun -- riwayat lama ATAU
     * versi future) ke baris BARU dengan effective_from HARI INI (F-40 TETAP
     * INSERT, bukan UPDATE) -- baris sumber TIDAK disentuh sama sekali, tetap
     * apa adanya di riwayat. Efeknya: versi itu langsung "aktif" mulai sekarang,
     * Boss cuma klik satu tombol, nol input tanggal manual.
     */
    public function activateNow(WorkSchedule $workSchedule): RedirectResponse
    {
        $today = now()->toDateString();

        // GUARD: baris utk hari ini SUDAH ada (mis. Boss klik tombol ini dua kali,
        // atau sudah ada versi 'Terjadwal' yang effective_from-nya kebetulan hari
        // ini) -- unique constraint DB bakal tolak insert kedua, tapi pesan error
        // jelas di sini LEBIH RAMAH daripada exception mentah dari constraint.
        if (WorkSchedule::where('effective_from', $today)->exists()) {
            throw ValidationException::withMessages([
                'effective_from' => 'Sudah ada versi yang mulai berlaku hari ini. Edit versi itu (baris berstatus "Terjadwal") kalau mau isinya beda.',
            ]);
        }

        WorkSchedule::create([
            'organization_id' => $workSchedule->organization_id,
            'effective_from' => $today,
            'days_of_week' => $workSchedule->days_of_week,
            'start_time' => $workSchedule->start_time,
            'end_time' => $workSchedule->end_time,
            'daily_capacity_minutes' => $workSchedule->daily_capacity_minutes,
            'created_by' => request()->user()->id,
        ]);

        return to_route('work-schedules.index');
    }
}

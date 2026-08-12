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
 *               ini, bukan toggle flag "aktif" lintas tanggal) + quickEdit() (audit
 *               Boss 2026-08-12, F-169: UI disederhanakan jadi 1 kartu "Jam Kerja
 *               Saat Ini" + tombol Edit -- Edit SELALU bikin versi baru efektif
 *               HARI INI, F-40 tetap INSERT, bukan timpa baris aktif). store()/
 *               update()/archive()/activateNow() DIPERTAHANKAN utuh di backend
 *               (route tetap ada) walau UI utama tidak lagi memakainya -- keputusan
 *               Boss supaya reversible, bukan dihapus.
 * DIPANGGIL   : routes/admin.php
 * MEMANGGIL   : WorkSchedule (INSERT untuk versi baru; UPDATE HANYA utk versi
 *               yang belum pernah aktif), ActivityLog + ActivityLogPresenter
 *               (feed "Log Perubahan" di index(), F-169)
 * DATA MASUK  : Form tambah/edit versi jam kerja, admin only
 * DATA KELUAR : Inertia page 'work-schedules/index' dengan versi AKTIF + log perubahan
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

use App\Http\Requests\WorkSchedule\QuickEditWorkScheduleRequest;
use App\Http\Requests\WorkSchedule\StoreWorkScheduleRequest;
use App\Http\Requests\WorkSchedule\UpdateWorkScheduleRequest;
use App\Models\ActivityLog;
use App\Models\WorkSchedule;
use App\Support\ActivityLogPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkScheduleController extends Controller
{
    /**
     * KONTRAK: 1 kartu versi AKTIF sekarang (WorkSchedule::active(), F-66) + feed
     * "Log Perubahan" (activity_logs subject WorkSchedule, F-169) -- REVISI
     * 2026-08-12 (audit Boss): sebelumnya kirim SELURUH riwayat versi ke tabel,
     * sekarang cuma versi aktif (riwayat versi tetap ada di DB & di route
     * store/update/archive/activateNow, cuma tidak lagi ditampilkan di sini).
     */
    public function index(Request $request): Response
    {
        // SUMBER: query otomatis ter-scope organization_id via OrganizationScope
        // (F-15) — tidak perlu where() manual di sini.
        $activeId = WorkSchedule::active($request->user()->organization_id)?->id;
        $current = $activeId ? WorkSchedule::with('creator')->find($activeId) : null;

        // SUMBER: F-85 -- with(['user','subject']) SEBELUM get(), ActivityLogPresenter
        // dibuat SEKALI dari collection yang sudah di-load (pola sama
        // ActivityLogController::index()), bukan query per baris.
        $logs = ActivityLog::query()
            ->where('subject_type', WorkSchedule::class)
            ->with(['user:id,name', 'subject'])
            ->latest('created_at')
            ->limit(30)
            ->get();

        $presenter = new ActivityLogPresenter($logs);

        return Inertia::render('work-schedules/index', [
            'current' => $current,
            'logs' => $logs->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'actor' => $log->user?->name ?? 'Sistem',
                'event_label' => ActivityLogPresenter::eventLabel($log->event),
                'message' => $presenter->describe($log),
                'created_at' => $log->created_at,
            ])->values(),
        ]);
    }

    /**
     * KONTRAK: "Edit" di kartu Jam Kerja Saat Ini (audit Boss 2026-08-12, F-169).
     * BUKAN update baris aktif (dilarang F-40) -- INSERT versi baru effective_from
     * HARI INI, isi field baru dari form. GUARD: kalau sudah ada versi utk hari
     * ini (mis. Boss sudah edit sekali hari ini), tolak dengan pesan jelas --
     * pola sama activateNow() (lihat komentar di sana), TANPA jalur ini
     * effective_from hari ini bisa dobel/unique constraint DB pecah mentah.
     */
    public function quickEdit(QuickEditWorkScheduleRequest $request): RedirectResponse
    {
        $today = now()->toDateString();

        if (WorkSchedule::where('effective_from', $today)->exists()) {
            throw ValidationException::withMessages([
                'daily_capacity_minutes' => 'Jam kerja sudah diubah hari ini — tunggu besok untuk mengubah lagi (F-40, mencegah jam kerja hari yang sama tertimpa dua kali).',
            ]);
        }

        WorkSchedule::create([
            ...$request->validated(),
            'effective_from' => $today,
            'created_by' => $request->user()->id,
        ]);

        return to_route('work-schedules.index');
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

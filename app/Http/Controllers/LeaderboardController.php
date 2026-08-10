<?php

/**
 * ==========================================================
 * MODUL       : LeaderboardController
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Halaman Leaderboard MANAGEMENT-ONLY (F-134, BLUEPRINT §7.2) —
 *               analisa produktivitas tim untuk management, BUKAN member (mereka
 *               tak melihatnya -> tak bisa menggame -> data tetap jujur). Skor
 *               PROVISIONAL sampai v1.5 dikalibrasi data nyata (F-2).
 * DIPANGGIL   : routes/admin.php (gated can:leaderboard.view — permission BARU,
 *               nol pemegang default TERMASUK admin, Boss assign manual lewat
 *               UI Role Management existing, F-135)
 * MEMANGGIL   : LeaderboardService, User
 * DATA MASUK  : query string ?from=Y-m-d&to=Y-m-d (opsional, default bulan
 *               berjalan WIB — F-69)
 * DATA KELUAR : Inertia props (from, to, rows[], kpi_enabled) — rows sudah urut
 *               Point desc. kpi_enabled (F-166) org-level -- frontend sembunyikan
 *               kolom KPI kalau false, TIDAK PERNAH dihitung ulang di controller ini.
 * RISIKO      : F-4 — TIDAK BOLEH ada field rupiah/gaji di props ini. Halaman ini
 *               skor RANKING, bukan nominal uang (itu v2.0).
 * ==========================================================
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function index(Request $request, LeaderboardService $service): Response
    {
        // GUARD: sama pola DashboardController::loadRows() -- Carbon::parse ketat
        // (format acak -> exception, bukan tanggal ngawur diam-diam). Default
        // BULAN BERJALAN (blueprint §7.2), WIB (F-69).
        $from = $request->query('from')
            ? Carbon::parse((string) $request->query('from'))->startOfDay()
            : Carbon::now()->startOfMonth();
        $to = $request->query('to')
            ? Carbon::parse((string) $request->query('to'))->endOfDay()
            : Carbon::now()->endOfMonth();

        // Roster TIM PENUH (pola sama DashboardController) -- leaderboard adalah
        // pandangan TIM, semua user aktif tampil walau nol task disetujui periode
        // ini (LeaderboardService::forPeriod() menjamin ini untuk Bottom-3).
        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('leaderboard/index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => $service->forPeriod($users, $from, $to),
            // F-166: master toggle org-level -- kolom KPI di frontend disembunyikan
            // total kalau false ("tinggal disable"), bukan ditampilkan kosong/nol.
            'kpi_enabled' => $request->user()->organization->kpi_enabled,
        ]);
    }
}

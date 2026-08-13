<?php

/**
 * ==========================================================
 * MODUL       : DashboardService
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Rumus dashboard admin/owner (02-DATA-MODEL §5, F-52) — kapasitas,
 *               beban, backlog, aktif, idle_plan, idle_real, anomali (F-53) per
 *               user untuk satu tanggal. Fondasi angka yang admin lihat di v0.8
 *               Hari-3 — service ini TIDAK merender UI apa pun.
 *               v1.0.1 (F-59/F-118): BEBAN kini SEBAR ke hari kerja sampai tenggat
 *               (bukan seluruh estimasi jatuh ke hari due/overdue) — lihat workloadSpread().
 *               v1.2 H3 Fase A (F-131): dailyLoadTotals() menambah beban AGREGAT per
 *               TANGGAL untuk grid heatmap kalender bulan — rumus SAMA (F-118), cuma
 *               digeneralisasi ke banyak tanggal dalam SATU query task (F-85).
 * DIPANGGIL   : DashboardController::summary(), DashboardController::commandCenter()
 * MEMANGGIL   : WorkSchedule, Holiday, Task, TaskTimeSegment, BusinessHoursCalculator
 * DATA MASUK  : Collection<User> (SATU organisasi — dijamin OrganizationScope F-15
 *               di titik panggilan), Carbon $date (default hari ini WIB)
 * DATA KELUAR : array keyed by user id -> metrik dashboard mentah (dipakai
 *               controller, BELUM dipetakan ke skor/rupiah — v1.5/v2.0)
 * RISIKO      : SUMBER F-96 (F-63 diputuskan) — BEBAN/BACKLOG dibagi rata jumlah
 *               assignee per-task SEBELUM dijumlah per user (bukan SUM/SUM, itu
 *               salah matematis), BARU disebar ke hari kerja (F-118, urutan WAJIB
 *               assignee dulu baru hari — lihat workloadSpread()). REALISASI & POIN
 *               TIDAK dibagi/disebar — beban murni PERENCANAAN (estimasi PENUH,
 *               bukan sisa kerja), progres tetap lewat counter (F-94), TIDAK dicampur.
 *               Salah di sini = admin assign task ke orang yang sebenarnya sudah
 *               penuh, atau dashboard bohong soal siapa yang idle.
 * ==========================================================
 */

namespace App\Services;

use App\Models\Holiday;
use App\Models\Task;
use App\Models\TaskTimeSegment;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * KONTRAK: rumus dashboard §5 LENGKAP untuk sekumpulan user sekaligus (F-85 —
     * dashboard tim, bukan satu user). Setiap sub-metrik dihitung dengan JUMLAH
     * QUERY TETAP (tidak tumbuh dengan jumlah user) — lihat masing-masing method
     * private, semuanya menerima Collection $users dan query SEKALI.
     *
     * @param  Collection<int, User>  $users  WAJIB satu organisasi yang sama (tidak
     *                                        divalidasi di sini — caller yang menjamin,
     *                                        biasanya lewat OrganizationScope F-15).
     * @param  Carbon|null  $date  Tanggal yang dilihat. Default SEKARANG (hari ini WIB).
     * @return array<int, array{kapasitas:int, aktif:int, beban:int, backlog:int, idle_plan:int, idle_real:int, anomalies:array}>
     */
    public function forUsers(Collection $users, ?Carbon $date = null): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $date = ($date ?? Carbon::now())->copy();

        // F-85: WorkSchedule + Holiday organisasi dimuat SEKALI di sini, dipakai
        // BERSAMA oleh workloadSpread() (F-118, hari kerja beban) DAN
        // realisasiBreakdown() (F-57, jam kerja realisasi) — bukan 2x query
        // terpisah seperti sebelum v1.0.1 (masing-masing method query sendiri).
        $organizationId = $users->first()->organization_id;
        $calculator = new BusinessHoursCalculator;
        // F-170 (audit Boss 2026-08-13): is_archived=false WAJIB disamakan dengan
        // WorkSchedule::active() (app/Models/WorkSchedule.php) -- tanpa filter ini,
        // versi FUTURE yang sudah diarsipkan admin (F-40) tetap bisa terpilih oleh
        // resolveScheduleForDay() untuk tanggal-tanggal di masa depan (mis. bulan
        // depan), bikin days_of_week hari kerja salah.
        $schedules = WorkSchedule::where('organization_id', $organizationId)->where('is_archived', false)->get();
        $holidays = Holiday::where('organization_id', $organizationId)->get();

        $kapasitas = $this->kapasitas($users, $date);
        [$beban, $backlog] = $this->workloadSpread($users, $date, $calculator, $schedules, $holidays);
        $realisasi = $this->realisasiBreakdown($users, $date, $calculator, $schedules, $holidays);
        $anomalies = $this->anomalies($users, $date);

        return $users->mapWithKeys(function (User $user) use ($kapasitas, $beban, $backlog, $realisasi, $anomalies) {
            $cap = $kapasitas[$user->id] ?? 0;
            $bebanUser = $beban[$user->id] ?? 0;
            $aktif = $realisasi[$user->id]['open'] ?? 0;
            $realisasiTotal = $aktif + ($realisasi[$user->id]['closed'] ?? 0);

            return [$user->id => [
                'kapasitas' => $cap,
                'aktif' => $aktif,
                'beban' => $bebanUser,
                'backlog' => $backlog[$user->id] ?? 0,
                // F-52: DUA IDLE, KEDUANYA BENAR — plan (assign) vs real (KPI).
                'idle_plan' => $cap - $bebanUser,
                'idle_real' => $cap - $realisasiTotal,
                'anomalies' => $anomalies[$user->id] ?? [],
            ]];
        })->all();
    }

    /**
     * KONTRAK: KAPASITAS = users.daily_capacity_minutes ?? work_schedule aktif
     * pada $date (F-40 versioned — pakai WorkSchedule::active(), BUKAN kolom
     * "capacity" statis). SATU query schedule untuk seluruh $users (semua user
     * satu organisasi pakai schedule organisasi yang sama).
     *
     * @return array<int, int> keyed by user id
     */
    public function kapasitas(Collection $users, Carbon $date): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $organizationId = $users->first()->organization_id;
        $schedule = WorkSchedule::active($organizationId, $date);
        $default = $schedule->daily_capacity_minutes ?? 0;

        return $users->mapWithKeys(fn (User $u) => [$u->id => $u->daily_capacity_minutes ?? $default])->all();
    }

    /**
     * KONTRAK: AKTIF = Σ realisasi segmen TERBUKA milik user (F-63/F-96: akumulasi
     * TOTAL dari started_at, sama seperti counter live F-94 — BUKAN slice per hari.
     * Keputusan Boss 2026-07-19, lihat 04-FINDING-REGISTRY LANGKAH 0 Hari-8).
     * Dipanggil MANDIRI (di luar forUsers()) — muat sendiri schedule/holiday,
     * TIDAK berbagi dengan forUsers() (yang sudah memuatnya sekali untuk dibagi
     * ke workloadSpread() DAN realisasiBreakdown(), F-85).
     *
     * @return array<int, int> keyed by user id
     */
    public function aktif(Collection $users, Carbon $date): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $organizationId = $users->first()->organization_id;
        $calculator = new BusinessHoursCalculator;
        // F-170 (audit Boss 2026-08-13): is_archived=false WAJIB disamakan dengan
        // WorkSchedule::active() (app/Models/WorkSchedule.php) -- tanpa filter ini,
        // versi FUTURE yang sudah diarsipkan admin (F-40) tetap bisa terpilih oleh
        // resolveScheduleForDay() untuk tanggal-tanggal di masa depan (mis. bulan
        // depan), bikin days_of_week hari kerja salah.
        $schedules = WorkSchedule::where('organization_id', $organizationId)->where('is_archived', false)->get();
        $holidays = Holiday::where('organization_id', $organizationId)->get();

        $breakdown = $this->realisasiBreakdown($users, $date, $calculator, $schedules, $holidays);

        return $users->mapWithKeys(fn (User $u) => [$u->id => $breakdown[$u->id]['open'] ?? 0])->all();
    }

    /**
     * KONTRAK: F-53 — task yang di-approve pada $date (approved_at, titik
     * actual_minutes DIBEKUKAN F-39) dengan actual_minutes > 3x estimated_minutes.
     * HANYA MENANDAI, tidak mengubah status/skor apa pun (lihat TaskObserver yang
     * SUDAH mencatat event 'anomaly_flagged' di activity_logs saat itu terjadi —
     * method ini query LANGSUNG ke tasks, bukan parsing log, karena
     * actual_minutes/estimated_minutes adalah kolom RAW yang murah di-query).
     *
     * @return array<int, array<int, array{task_id:int, title:string, estimated_minutes:int, actual_minutes:int}>> keyed by user id
     */
    public function anomalies(Collection $users, Carbon $date): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $userIds = $users->pluck('id')->all();

        $tasks = Task::query()
            ->whereDate('approved_at', $date)
            ->whereNotNull('actual_minutes')
            // MAGIC NUMBER: 3x — ambang anomali F-53, keputusan Boss. Dibandingkan
            // di SQL (bukan ditarik semua lalu difilter PHP) supaya tidak menyeret
            // task normal yang jumlahnya jauh lebih banyak ke memori.
            ->whereRaw('actual_minutes > estimated_minutes * 3')
            ->whereHas('assignees', fn ($q) => $q->whereIn('users.id', $userIds))
            ->with('assignees:id')
            ->get(['id', 'title', 'estimated_minutes', 'actual_minutes']);

        $result = [];

        foreach ($tasks as $task) {
            foreach ($task->assignees as $assignee) {
                if (! in_array($assignee->id, $userIds, true)) {
                    continue;
                }

                $result[$assignee->id][] = [
                    'task_id' => $task->id,
                    'title' => $task->title,
                    'estimated_minutes' => $task->estimated_minutes,
                    'actual_minutes' => $task->actual_minutes,
                ];
            }
        }

        return $result;
    }

    /**
     * KONTRAK: F-59/F-118 — SATU query untuk SEMUA task belum-`is_completed` yang
     * di-assign ke $users, TANPA filter due_date (beda dari rumus lama yang
     * membelah "due hari ini/overdue" vs "due masa depan" jadi 2 query terpisah)
     * — sekarang SETIAP task berpotensi menyumbang ke KEDUA bucket sekaligus (F-118:
     * sebagian ke beban hari ini, sisanya ke backlog), jadi harus dihitung SEKALI
     * per task, bukan dua kali dengan filter berbeda.
     *
     * URUTAN WAJIB per task:
     *   1. F-96a — bagi estimated_minutes rata jumlah ASSIGNEE dulu ("per_assignee_total").
     *      Assignee count dihitung dari SELURUH assignee task (bukan cuma yang ada
     *      di $users) — assignee di luar $users tetap ikut membagi porsi, cuma
     *      kontribusinya tidak dilaporkan balik karena bukan bagian permintaan ini.
     *   2. F-118 — BARU sebar per_assignee_total itu ke jumlah HARI KERJA dari
     *      HARI INI s/d due_date INKLUSIF (BusinessHoursCalculator::countBusinessDays(),
     *      REUSE F-43/F-72 — bukan kalkulator baru). max(1, ...) MENJAMIN 3 kasus
     *      tepi (A3) otomatis benar TANPA cabang khusus: overdue (due < hari ini
     *      -> countBusinessDays kembalikan 0 -> dipaksa 1), tenggat hari ini yang
     *      kebetulan bukan hari kerja mis. Sabtu (juga 0 -> dipaksa 1), dan tenggat
     *      hari ini yang memang hari kerja (1 -> tetap 1) — semuanya jatuh ke
     *      "seluruh porsi hari ini", persis seperti yang diminta.
     *
     * ESTIMASI PENUH dipakai (bukan sisa kerja) — beban murni metrik PERENCANAAN,
     * TIDAK dikurangi progres. Progres tetap tampil terpisah lewat counter (F-94),
     * SENGAJA tidak dicampur ke sini.
     *
     * @param  Collection<int, WorkSchedule>  $schedules  SELURUH versi, dimuat SEKALI oleh forUsers() (F-85).
     * @param  Collection<int, Holiday>  $holidays  SELURUH holiday organisasi, dimuat SEKALI oleh forUsers() (F-85).
     * @return array{0: array<int, int>, 1: array<int, int>} [beban, backlog] keyed by user id
     */
    private function workloadSpread(Collection $users, Carbon $date, BusinessHoursCalculator $calculator, Collection $schedules, Collection $holidays): array
    {
        if ($users->isEmpty()) {
            return [[], []];
        }

        $userIds = $users->pluck('id')->all();

        $tasks = Task::query()
            ->whereHas('taskStatus', fn ($q) => $q->where('is_completed', false))
            ->whereHas('assignees', fn ($q) => $q->whereIn('users.id', $userIds))
            ->with('assignees:id')
            ->get(['id', 'estimated_minutes', 'due_date']);

        $today = $date->copy()->startOfDay();
        $bebanShares = [];
        $backlogShares = [];

        foreach ($tasks as $task) {
            // GUARD: assignee count minimal 1 — StoreTaskRequest mewajibkan
            // assignee (F-86 seeder sudah dibereskan), tapi dijaga di sini
            // supaya divisi tidak pernah pecah kalau ada data lama/edge case.
            $assigneeCount = max($task->assignees->count(), 1);
            $perAssigneeTotal = $task->estimated_minutes / $assigneeCount; // F-96 DULU

            $dueDate = $task->due_date->copy()->startOfDay();
            $hariKerja = max(1, $calculator->countBusinessDays($today, $dueDate, $schedules, $holidays)); // F-118 BARU

            $kontribusiHariIni = $perAssigneeTotal / $hariKerja;
            $sisaBacklog = $perAssigneeTotal - $kontribusiHariIni;

            foreach ($task->assignees as $assignee) {
                if (! in_array($assignee->id, $userIds, true)) {
                    continue;
                }

                $bebanShares[$assignee->id] = ($bebanShares[$assignee->id] ?? 0) + $kontribusiHariIni;
                $backlogShares[$assignee->id] = ($backlogShares[$assignee->id] ?? 0) + $sisaBacklog;
            }
        }

        return [
            array_map(fn (float $v) => (int) round($v), $bebanShares),
            array_map(fn (float $v) => (int) round($v), $backlogShares),
        ];
    }

    /**
     * KONTRAK: F-131 — beban AGREGAT (Σ semua $users) per TANGGAL, untuk grid
     * heatmap kalender bulan. Nilai tiap tanggal MATEMATIS IDENTIK dengan
     * menjumlah workloadSpread($users, $tanggalItu)['beban'] tiap user satu-per-satu
     * (F-109/F-118, satu sumber rumus) — beda dari workloadSpread() cuma di CARA
     * PEROLEHAN: task di-query SEKALI untuk SELURUH $dates (F-85, A9 — dilarang
     * loop-query per hari/user), bukan sekali per tanggal.
     *
     * PEMBULATAN WAJIB PER-USER-PER-TANGGAL (bukan dibulatkan sekali di akhir
     * setelah dijumlah semua user) — supaya identik bit demi bit dengan menjumlah
     * hasil workloadSpread() yang juga membulatkan per user (lihat array_map round()
     * di workloadSpread()). Membulatkan di titik berbeda bisa menghasilkan selisih
     * ±1..N menit dari salah pembulatan, melanggar syarat "identik" F-109.
     *
     * $dates WAJIB tanggal >= hari ini — F-131: hari LEWAT = NETRAL, tidak pernah
     * dihitung. Method ini tidak tahu konsep "netral"; caller (controller) yang
     * menyaring tanggal SEBELUM memanggil.
     *
     * F-170 (audit Boss 2026-08-13): task OVERDUE (due_date < $today) dulu numpuk
     * beban PENUH ke SETIAP $date yang dicek (bukan cuma sekali) — imbasnya kalender
     * bulan DEPAN ikut kelihatan overload walau tidak ada task nyata di bulan itu,
     * murni gara-gara task lama yang belum selesai. FIX: beban penuh task overdue
     * HANYA jatuh ke tanggal REAL "hari ini" ($today) — tanggal lain (termasuk
     * bulan depan) tidak lagi menanggung task yang sudah lewat tenggat. Spread
     * normal (belum lewat tenggat) TIDAK berubah.
     *
     * @param  Collection<int, User>  $users  WAJIB satu organisasi (F-15, sama seperti forUsers()).
     * @param  Collection<int, Carbon>  $dates  tanggal (vantage point beban F-118 per tanggal itu).
     * @param  Carbon  $today  tanggal REAL saat ini (F-69 WIB) — beda dari $dates saat caller
     *                         sedang melihat bulan lain; dipakai murni untuk guard F-170 di atas.
     * @return array<string, int> keyed 'Y-m-d' -> total beban SEMUA $users pada tanggal itu (menit)
     */
    public function dailyLoadTotals(Collection $users, Collection $dates, Carbon $today): array
    {
        if ($users->isEmpty() || $dates->isEmpty()) {
            return [];
        }

        $organizationId = $users->first()->organization_id;
        $calculator = new BusinessHoursCalculator;
        // F-170 (audit Boss 2026-08-13): is_archived=false WAJIB disamakan dengan
        // WorkSchedule::active() (app/Models/WorkSchedule.php) -- tanpa filter ini,
        // versi FUTURE yang sudah diarsipkan admin (F-40) tetap bisa terpilih oleh
        // resolveScheduleForDay() untuk tanggal-tanggal di masa depan (mis. bulan
        // depan), bikin days_of_week hari kerja salah.
        $schedules = WorkSchedule::where('organization_id', $organizationId)->where('is_archived', false)->get();
        $holidays = Holiday::where('organization_id', $organizationId)->get();
        $userIds = $users->pluck('id')->all();

        // SATU query, isinya SAMA PERSIS dengan query di workloadSpread() —
        // diulang literal di sini (bukan memanggil workloadSpread() yang private
        // & per-tanggal) supaya SELURUH $dates berbagi SATU hasil fetch task (F-85),
        // bukan query ulang per tanggal di grid bulan (bisa ~30 tanggal).
        $tasks = Task::query()
            ->whereHas('taskStatus', fn ($q) => $q->where('is_completed', false))
            ->whereHas('assignees', fn ($q) => $q->whereIn('users.id', $userIds))
            ->with('assignees:id')
            ->get(['id', 'estimated_minutes', 'due_date']);

        // [userId][dateKey] => float mentah (belum dibulatkan) — dijumlah semua
        // task milik user itu DULU per tanggal, baru dibulatkan (lihat komentar KONTRAK).
        $userDateRaw = [];

        $todayKey = $today->copy()->startOfDay();

        foreach ($tasks as $task) {
            $assigneeCount = max($task->assignees->count(), 1);
            $perAssigneeTotal = $task->estimated_minutes / $assigneeCount; // F-96 DULU
            $dueDate = $task->due_date->copy()->startOfDay();

            foreach ($task->assignees as $assignee) {
                if (! in_array($assignee->id, $userIds, true)) {
                    continue;
                }

                foreach ($dates as $date) {
                    $vantage = $date->copy()->startOfDay();

                    if ($vantage->greaterThan($dueDate)) {
                        // F-170: tenggat task ini sudah lewat DARI SUDUT PANDANG
                        // tanggal ini. Beban penuh cuma boleh jatuh kalau tanggal
                        // ini benar-benar "hari ini" ($today) -- tanggal lain
                        // (mis. bulan depan) tidak retroaktif menanggung task lama.
                        $kontribusiHariItu = $vantage->equalTo($todayKey) ? $perAssigneeTotal : 0.0;
                    } else {
                        $hariKerja = max(1, $calculator->countBusinessDays($vantage, $dueDate, $schedules, $holidays)); // F-118 BARU
                        $kontribusiHariItu = $perAssigneeTotal / $hariKerja;
                    }

                    if ($kontribusiHariItu === 0.0) {
                        continue;
                    }

                    $key = $date->toDateString();
                    $userDateRaw[$assignee->id][$key] = ($userDateRaw[$assignee->id][$key] ?? 0) + $kontribusiHariItu;
                }
            }
        }

        $totals = [];
        foreach ($dates as $date) {
            $totals[$date->toDateString()] = 0;
        }

        foreach ($userDateRaw as $byDate) {
            foreach ($byDate as $key => $raw) {
                $totals[$key] += (int) round($raw); // bulat PER USER PER TANGGAL, lalu jumlah
            }
        }

        return $totals;
    }

    /**
     * KONTRAK: satu query task_time_segments untuk seluruh $users sekaligus,
     * dipecah 'open' (segmen masih berjalan, akumulasi F-57 sampai sekarang) vs
     * 'closed' (segmen yang DITUTUP pada $date). Segmen closed di hari LAIN tidak
     * ikut terhitung "hari itu" — sudah tersalur ke hari saat ditutup. Segmen
     * open cuma masuk hitungan kalau $date = hari ini (tidak ada state historis
     * "sedang berjalan" untuk tanggal lampau, F-38 — cuma timestamp, bukan state).
     *
     * @param  Collection<int, WorkSchedule>  $schedules  SELURUH versi, dimuat SEKALI oleh caller (F-85).
     * @param  Collection<int, Holiday>  $holidays  SELURUH holiday organisasi, dimuat SEKALI oleh caller (F-85).
     * @return array<int, array{open:int, closed:int}> keyed by user id
     */
    private function realisasiBreakdown(Collection $users, Carbon $date, BusinessHoursCalculator $calculator, Collection $schedules, Collection $holidays): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $userIds = $users->pluck('id')->all();
        $isToday = $date->isSameDay(Carbon::now());

        $segments = TaskTimeSegment::whereIn('user_id', $userIds)
            ->where(function ($q) use ($date, $isToday) {
                $q->whereDate('ended_at', $date);

                if ($isToday) {
                    $q->orWhereNull('ended_at');
                }
            })
            ->get(['user_id', 'started_at', 'ended_at']);

        $breakdown = [];

        foreach ($segments as $segment) {
            $minutes = $calculator->overlapMinutes($segment->started_at, $segment->ended_at, $schedules, $holidays);
            $key = $segment->ended_at === null ? 'open' : 'closed';
            $breakdown[$segment->user_id][$key] = ($breakdown[$segment->user_id][$key] ?? 0) + $minutes;
        }

        return $breakdown;
    }
}

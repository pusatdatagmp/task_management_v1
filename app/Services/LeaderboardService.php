<?php

/**
 * ==========================================================
 * MODUL       : LeaderboardService
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Rumus leaderboard v1.2/v1.5 (F-134, BLUEPRINT §7.2) — Level 1:
 *               Point = Σ points task DISETUJUI dalam periode (data BEKU F-39,
 *               task belum-selesai tidak pernah masuk). Kolom konteks (Rating/
 *               Revisi/Ditolak/On-time%/KPI) dihitung TERPISAH, TIDAK dibaur ke
 *               Point (F-62/F-168 — konteks bukan hukuman tersembunyi, kpi_total
 *               KOLOM TERPISAH bukan pengganti Point). Skor di sini PROVISIONAL
 *               (F-2) — kalibrasi final v1.5 dari data nyata, bukan tugas service ini.
 * DIPANGGIL   : LeaderboardController::index()
 * MEMANGGIL   : Task (isOnTime(), F-109), User (Collection, sudah difilter organisasi
 *               oleh caller — F-15)
 * DATA MASUK  : Collection<User> (satu organisasi), rentang tanggal $from/$to (approved_at)
 * DATA KELUAR : array per user: point/rating/revisi/ditolak/on_time_percent/kpi_total
 *               (angka MENTAH — pemetaan rupiah/skor-kinerja lain TIDAK PERNAH terjadi
 *               di sini atau di mana pun, F-4/F-134). kpi_total = Σ kpi_score task
 *               disetujui periode ini, KOLOM TERPISAH dari point (F-168).
 * RISIKO      : SUMBER on-time — logika (F-47 coalesce original_due_date??due_date,
 *               2026-08-07 basis submitted_at??approved_at) SEKARANG hidup di
 *               Task::isOnTime() (diekstrak v1.4 KPI-1, F-109) supaya SimpleTimelinessStrategy
 *               bisa reuse PERSIS logika yang sama saat freeze kpi_score di approve() —
 *               JANGAN tulis ulang logika on-time inline di sini lagi, itu bikin
 *               penentu on-time kedua yang bisa drift dari yang dipakai KPI.
 * ==========================================================
 */

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LeaderboardService
{
    /**
     * KONTRAK: SATU query task (F-85 — batch, bukan loop per-user) untuk seluruh
     * $users sekaligus, di-with() assignees supaya N+1 tidak tumbuh dengan jumlah
     * user/task. SETIAP user aktif WAJIB tetap muncul di hasil walau nol task
     * disetujui di periode ini (point=0, kolom konteks null) — Bottom-3 (blueprint
     * §7.2) justru butuh melihat siapa yang nol produktivitas periode ini, bukan
     * cuma yang punya data.
     *
     * @param  Collection<int, User>  $users  WAJIB satu organisasi (F-15, caller menjamin).
     * @return array<int, array{id:int, name:string, point:int, rating:?float, revisi:int, ditolak:int, on_time_percent:?float, kpi_total:int}>
     */
    public function forPeriod(Collection $users, Carbon $from, Carbon $to): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $userIds = $users->pluck('id')->all();

        // F-39: hanya task DISETUJUI (is_completed true DIJAMIN approved_at terisi,
        // lihat TaskTransitionService::approve() -- keduanya di-set dalam SATU
        // update()) yang approved_at-nya jatuh di periode filter.
        $tasks = Task::query()
            ->whereHas('taskStatus', fn ($q) => $q->where('is_completed', true))
            ->whereBetween('approved_at', [$from, $to])
            ->whereHas('assignees', fn ($q) => $q->whereIn('users.id', $userIds))
            ->with('assignees:id')
            ->get(['id', 'points', 'quality_rating', 'rejection_count', 'due_date', 'original_due_date', 'submitted_at', 'approved_at', 'kpi_score']);

        // Akumulator mentah per user -- SATU pass PHP di memori, bukan query lagi
        // per task/user (F-85). ratingSum/ratingCount terpisah supaya rata-rata
        // dihitung SETELAH loop (bukan running-average yang rawan salah bobot).
        $acc = [];
        foreach ($userIds as $id) {
            $acc[$id] = ['point' => 0, 'rating_sum' => 0, 'rating_count' => 0, 'revisi' => 0, 'ditolak' => 0, 'approved_total' => 0, 'on_time' => 0, 'kpi_total' => 0];
        }

        foreach ($tasks as $task) {
            // F-47/F-109: on-time diekstrak ke Task::isOnTime() (v1.4 KPI-1) supaya
            // SimpleTimelinessStrategy bisa reuse persis logika yang sama saat freeze
            // di approve() -- SATU sumber, nol penentu on-time kedua. Perilaku method
            // ini IDENTIK dengan inline lama (original_due_date??due_date,
            // submitted_at??approved_at), lihat header Task::isOnTime().
            $onTime = $task->isOnTime();

            foreach ($task->assignees as $assignee) {
                if (! in_array($assignee->id, $userIds, true)) {
                    continue;
                }

                // F-63b: point UTUH ke tiap assignee (bukan dibagi seperti F-96
                // estimated_minutes) -- dorong kolaborasi, lawan Goodhart F-4.
                $acc[$assignee->id]['point'] += $task->points;
                $acc[$assignee->id]['rating_sum'] += $task->quality_rating;
                $acc[$assignee->id]['rating_count']++;
                // F-39: rejection_count sudah BEKU sejak approve, dijumlah apa adanya.
                $acc[$assignee->id]['revisi'] += $task->rejection_count;
                $acc[$assignee->id]['ditolak'] += $task->rejection_count > 0 ? 1 : 0;
                $acc[$assignee->id]['approved_total']++;
                $acc[$assignee->id]['on_time'] += $onTime ? 1 : 0;
                // F-168: kpi_score task BEKU sejak approve (F-167/F-39) dijumlah apa
                // adanya -- null (task disetujui saat kpi_enabled=false, lihat header
                // migrasi tasks.kpi_score) diperlakukan 0 kontribusi, BUKAN dilempar
                // exception -- data lama dari periode toggle nonaktif tetap valid tampil.
                $acc[$assignee->id]['kpi_total'] += $task->kpi_score ?? 0;
            }
        }

        return $users->map(function (User $user) use ($acc) {
            $row = $acc[$user->id];

            return [
                'id' => $user->id,
                'name' => $user->name,
                'point' => $row['point'],
                // F-62: kolom KONTEKS -- null kalau nol task disetujui periode ini
                // (bukan 0, supaya frontend bisa tampilkan "-" alih-alih "0.0" yang
                // menyesatkan seolah dinilai rendah padahal memang tak ada data).
                'rating' => $row['rating_count'] > 0 ? round($row['rating_sum'] / $row['rating_count'], 2) : null,
                'revisi' => $row['revisi'],
                'ditolak' => $row['ditolak'],
                'on_time_percent' => $row['approved_total'] > 0 ? round($row['on_time'] / $row['approved_total'] * 100, 1) : null,
                // F-168: kolom TERPISAH dari 'point' -- lihat catatan kpi_total di atas.
                'kpi_total' => $row['kpi_total'],
            ];
        })->sortByDesc('point')->values()->all();
    }
}

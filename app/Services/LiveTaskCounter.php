<?php

/**
 * ==========================================================
 * MODUL       : LiveTaskCounter
 * KLASIFIKASI : DOMAIN
 * TUJUAN      : Snapshot counter live untuk UI (badge "Sedang dikerjakan — 1j 23m")
 *               di task yang punya segmen kerja terbuka. F-94 — TIDAK menghitung
 *               business-hours sendiri, 100% delegasi ke BusinessHoursCalculator
 *               yang sudah matang (F-57/F-66/F-43). Method ini HANYA membaca, TIDAK
 *               PERNAH menulis actual_minutes — beda total dari
 *               Task::calculateActualMinutes() (dipakai TaskObserver untuk FREEZE, F-39).
 * DIPANGGIL   : TaskController::show()/myTasks()/index()
 * MEMANGGIL   : BusinessHoursCalculator, WorkSchedule, Holiday, TaskTimeSegment
 * DATA MASUK  : Collection<Task> (WAJIB eager-load relasi taskStatus) + User yang login
 * DATA KELUAR : array keyed by task id -> data counter (atau null kalau task bukan
 *               is_work_state ATAU user ini tidak sedang punya segmen terbuka di situ)
 * RISIKO      : SUMBER F-85 — dipanggil dari List View/My Tasks yang bisa berisi
 *               puluhan task sekaligus. SELURUH query (schedules, holidays, segmen)
 *               di-batch SEKALI di luar loop per-task — bukan query per task.
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

class LiveTaskCounter
{
    /**
     * KONTRAK: hitung counter live untuk SEKUMPULAN task sekaligus. F-63 TIDAK
     * berlaku di sini — F-63 soal agregasi lintas-assignee untuk dashboard,
     * method ini SELALU per-USER: 2 assignee di task yang sama masing-masing
     * dapat angka MEREKA SENDIRI (segmen milik mereka), bukan gabungan (B5).
     *
     * @param  Collection<int, Task>  $tasks  WAJIB sudah eager-load relasi taskStatus.
     * @return array<int, array{accumulated_minutes:int, is_in_work_window:bool, window_ends_at:?Carbon, segment_started_at:Carbon}|null> keyed by task id
     */
    public function forTasks(Collection $tasks, User $user): array
    {
        $workStateTasks = $tasks->filter(fn (Task $task) => $task->taskStatus?->is_work_state);

        if ($workStateTasks->isEmpty()) {
            return [];
        }

        $organizationId = $user->organization_id;
        $calculator = new BusinessHoursCalculator;
        $schedules = WorkSchedule::where('organization_id', $organizationId)->get();
        $holidays = Holiday::where('organization_id', $organizationId)->get();

        // F-85: SATU query untuk seluruh koleksi task, bukan per task di loop.
        $segmentsByTask = TaskTimeSegment::whereIn('task_id', $workStateTasks->pluck('id'))
            ->where('user_id', $user->id)
            ->get()
            ->groupBy('task_id');

        $now = Carbon::now();

        // SUMBER: "sekarang di dalam jendela kerja?" sama untuk SEMUA task dalam
        // satu request (cuma bergantung organisasi+waktu sekarang) — dihitung
        // SEKALI di luar loop. Trik: overlap 1-menit [now, now+1) > 0 berarti
        // sekarang jatuh di hari kerja + jam kerja + bukan libur — satu sumber
        // kebenaran (calculator itu sendiri), bukan pengecekan days_of_week/holiday
        // paralel yang bisa drift (F-72/F-76).
        $isInWorkWindow = $calculator->overlapMinutes($now, $now->copy()->addMinute(), $schedules, $holidays) > 0;
        $todaySchedule = WorkSchedule::active($organizationId, $now);
        $windowEndsAt = $todaySchedule
            ? $now->copy()->startOfDay()->setTimeFromTimeString((string) $todaySchedule->end_time)
            : null;

        return $workStateTasks->mapWithKeys(function (Task $task) use ($segmentsByTask, $calculator, $schedules, $holidays, $isInWorkWindow, $windowEndsAt) {
            $segments = $segmentsByTask->get($task->id, collect());
            $openSegment = $segments->first(fn (TaskTimeSegment $s) => $s->ended_at === null);

            // F-48: maks 1 segmen terbuka per task -- tapi bisa jadi terbuka milik
            // USER LAIN (multi-assignee), bukan $user. Null di sini = $user tidak
            // sedang mengerjakan task ini SEKARANG, walau task-nya is_work_state.
            if (! $openSegment) {
                return [$task->id => null];
            }

            // SUMBER: closedMinutes = Σ segmen milik $user yang SUDAH ditutup (pola
            // tolak->kerja-lagi, F-41). openMinutes = kontribusi segmen yang MASIH
            // terbuka DIHITUNG SAMPAI SEKARANG lewat overlapMinutes($end=null) —
            // method itu SUDAH menangani cap jendela (F-57)/holiday (F-43)/config
            // per-hari (F-66) sendiri, live counter tidak menduplikasi logika itu.
            $closedMinutes = $segments
                ->filter(fn (TaskTimeSegment $s) => $s->ended_at !== null)
                ->sum(fn (TaskTimeSegment $s) => $calculator->overlapMinutes($s->started_at, $s->ended_at, $schedules, $holidays));

            $openMinutes = $calculator->overlapMinutes($openSegment->started_at, null, $schedules, $holidays);

            return [$task->id => [
                // Total REALISASI MILIK $user AS OF SEKARANG (server time) — frontend
                // tinggal menambah selisih wall-clock sejak halaman dimuat (kalau
                // is_in_work_window) sampai window_ends_at, TANPA logika business-hours.
                'accumulated_minutes' => $closedMinutes + $openMinutes,
                'is_in_work_window' => $isInWorkWindow,
                'window_ends_at' => $windowEndsAt,
                'segment_started_at' => $openSegment->started_at,
            ]];
        })->all();
    }

    /**
     * KONTRAK: varian 1 task, dipakai halaman Detail Task (show()) — bungkus
     * tipis di atas forTasks() supaya nol logika ganda antara halaman single
     * dan halaman list (My Tasks/List View).
     */
    public function forTask(Task $task, User $user): ?array
    {
        return $this->forTasks(collect([$task]), $user)[$task->id] ?? null;
    }
}

// ==========================================================
// MODUL       : task-live-counter
// KLASIFIKASI : UI
// TUJUAN      : Tampilkan counter realisasi LIVE (F-94) — dipakai BERSAMA oleh
//               tasks/show.tsx (badge besar, B1), tasks/my-tasks.tsx (badge kecil,
//               B2), tasks/index.tsx (dot ringkas, B3) supaya ketiganya lewat satu
//               komponen & satu hook (use-live-counter), bukan tiga implementasi
//               tick yang bisa drift. H7 (F-138b/f): tambah state JEDA (abu-abu)
//               lewat prop `isPaused` OPSIONAL — default false supaya my-tasks/
//               index/all/board (belum mengirim `work_state` task-wide dari
//               server) berperilaku IDENTIK sebelum H7, nol regresi di sana.
// DIPANGGIL   : pages/tasks/{show,my-tasks,index,all,board}.tsx — HANYA show.tsx
//               yang mengirim isPaused NYATA (dari task.work_state, H7 scope
//               "4 tombol di detail task" — lihat prompt H7 DoD).
// MEMANGGIL   : hooks/use-live-counter (tick lokal, F-38 — nol polling)
// DATA MASUK  : isWorkState (flag task_status.is_work_state, F-44), liveCounter
//               (null kalau user login tidak sedang punya segmen terbuka di task
//               ini — B5/B6, BUKAN dicek di sini, sudah diputuskan server),
//               isPaused (H7: task.work_state==='dikerjakan-jeda', task-wide —
//               BEDA dari liveCounter yang per-user)
// DATA KELUAR : -
// RISIKO      : `isWorkState` HARUS dibaca dari task_status.is_work_state (flag),
//               JANGAN pernah dari nama status (F-44) — komponen ini sengaja
//               menerima boolean siap pakai, bukan objek status, supaya pemanggil
//               tidak tergoda membandingkan nama. `isPaused` task-wide (BUKAN
//               "apakah SAYA jeda") — assignee lain masih bisa sedang aktif di
//               task yang sama walau segmen SAYA sendiri ditutup; jangan disamakan.
// ==========================================================

import { Badge } from '@/components/ui/badge';
import { formatLiveMinutes, useLiveCounter, type LiveCounterData } from '@/hooks/use-live-counter';

interface TaskLiveCounterProps {
    isWorkState: boolean;
    liveCounter: LiveCounterData | null;
    isPaused?: boolean;
    /** 'badge' = besar (detail/my-tasks, B1/B2) · 'dot' = ringkas (List View, B3). */
    variant?: 'badge' | 'dot';
}

export default function TaskLiveCounter({ isWorkState, liveCounter, isPaused = false, variant = 'badge' }: TaskLiveCounterProps) {
    const minutes = useLiveCounter(liveCounter);

    if (!isWorkState) {
        return null;
    }

    // H7/F-138f: JEDA = abu-abu, task-wide -- diperiksa SEBELUM render "aktif"
    // (hijau), supaya nol segmen terbuka SIAPA PUN selalu menang tampil sebagai
    // jeda, bukan diam-diam kelihatan seperti masih berjalan.
    if (isPaused) {
        if (variant === 'dot') {
            return (
                <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground" title="Jeda">
                    <span className="h-2 w-2 rounded-full bg-slate-400" />
                    Jeda
                </span>
            );
        }

        return (
            <Badge className="border-transparent bg-slate-500 px-3 py-1 text-sm text-white hover:bg-slate-500">Jeda</Badge>
        );
    }

    if (variant === 'dot') {
        return (
            <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground" title="Sedang dikerjakan">
                <span className="h-2 w-2 rounded-full bg-emerald-500" />
                {minutes !== null && formatLiveMinutes(minutes)}
            </span>
        );
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Badge className="border-transparent bg-emerald-600 px-3 py-1 text-sm text-white hover:bg-emerald-600">
                {minutes !== null ? `Sedang dikerjakan — ${formatLiveMinutes(minutes)}` : 'Sedang dikerjakan'}
            </Badge>
            {/* A2: is_in_work_window=false -> angka statis (di atas), TAPI badge ini
                jelaskan KENAPA tidak menick, supaya bukan terlihat seperti bug. */}
            {liveCounter && !liveCounter.is_in_work_window && (
                <span className="text-xs text-muted-foreground">di luar jam kerja</span>
            )}
        </div>
    );
}

export type { LiveCounterData };

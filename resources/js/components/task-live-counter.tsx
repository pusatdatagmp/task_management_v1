// ==========================================================
// MODUL       : task-live-counter
// KLASIFIKASI : UI
// TUJUAN      : Tampilkan counter realisasi LIVE (F-94) — dipakai BERSAMA oleh
//               tasks/show.tsx (badge besar, B1), tasks/my-tasks.tsx (badge kecil,
//               B2), tasks/index.tsx (dot ringkas, B3) supaya ketiganya lewat satu
//               komponen & satu hook (use-live-counter), bukan tiga implementasi
//               tick yang bisa drift.
// DIPANGGIL   : pages/tasks/{show,my-tasks,index}.tsx
// MEMANGGIL   : hooks/use-live-counter (tick lokal, F-38 — nol polling)
// DATA MASUK  : isWorkState (flag task_status.is_work_state, F-44), liveCounter
//               (null kalau user login tidak sedang punya segmen terbuka di task
//               ini — B5/B6, BUKAN dicek di sini, sudah diputuskan server)
// DATA KELUAR : -
// RISIKO      : `isWorkState` HARUS dibaca dari task_status.is_work_state (flag),
//               JANGAN pernah dari nama status (F-44) — komponen ini sengaja
//               menerima boolean siap pakai, bukan objek status, supaya pemanggil
//               tidak tergoda membandingkan nama.
// ==========================================================

import { formatLiveMinutes, useLiveCounter, type LiveCounterData } from '@/hooks/use-live-counter';
import { Badge } from '@/components/ui/badge';

interface TaskLiveCounterProps {
    isWorkState: boolean;
    liveCounter: LiveCounterData | null;
    /** 'badge' = besar (detail/my-tasks, B1/B2) · 'dot' = ringkas (List View, B3). */
    variant?: 'badge' | 'dot';
}

export default function TaskLiveCounter({ isWorkState, liveCounter, variant = 'badge' }: TaskLiveCounterProps) {
    const minutes = useLiveCounter(liveCounter);

    if (!isWorkState) {
        return null;
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

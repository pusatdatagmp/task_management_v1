// ==========================================================
// MODUL       : task-work-actions
// KLASIFIKASI : UI
// TUJUAN      : 4 tombol Mulai/Jeda/Lanjut/Submit (H7/F-132/F-138) — detail task,
//               ASSIGNEE-ONLY (F-95). Tombol beda per work_state: todo -> Mulai ·
//               dikerjakan-aktif -> Jeda+Submit · dikerjakan-jeda -> Lanjut+Submit ·
//               review/selesai -> tak ada tombol (F-132 tabel state machine).
//               Label "Jeda" (2026-08-07, keputusan Boss: UI wajib Bahasa Indonesia)
//               — route/endpoint TETAP "tasks.hold" (kontrak API tidak berubah).
// DIPANGGIL   : pages/tasks/show.tsx
// MEMANGGIL   : route('tasks.start'/'tasks.hold'/'tasks.resume'/'tasks.submit')
//               (TaskController -> TaskTransitionService)
// DATA MASUK  : workState (task.work_state, 5-nilai task-wide, F-138b — DIHITUNG
//               SERVER, komponen ini TIDAK menebak status dari nama), isAssignee
//               (dihitung pemanggil dari task.assignees vs auth.user.id)
// DATA KELUAR : PATCH tanpa body -> TaskTransitionService, redirect back()
// RISIKO      : Gate F-127 (Submit) ditegakkan SERVER di TaskTransitionService —
//               kegagalan (checklist belum tuntas) tampil lewat showError() dari
//               response error, BUKAN validasi client (client sengaja tidak tahu
//               state checklist real-time di sini, F-111 — satu sumber kebenaran).
// ==========================================================

import { Button } from '@/components/ui/button';
import { showError } from '@/lib/swal';
import { router } from '@inertiajs/react';

type WorkState = 'todo' | 'dikerjakan-aktif' | 'dikerjakan-jeda' | 'review' | 'selesai';

interface TaskWorkActionsProps {
    projectId: number;
    taskId: number;
    workState: WorkState;
    isAssignee: boolean;
}

export default function TaskWorkActions({ projectId, taskId, workState, isAssignee }: TaskWorkActionsProps) {
    // F-95: tombol HANYA assignee. review/selesai: F-132 tabel — tak ada tombol,
    // approve/reject admin lewat TaskStatusCell (komponen terpisah, tak diubah H7).
    if (!isAssignee || workState === 'review' || workState === 'selesai') {
        return null;
    }

    const call = (routeName: string) => {
        router.patch(
            route(routeName, [projectId, taskId]),
            {},
            {
                preserveScroll: true,
                onError: (errors) => showError(errors.task_status_id ?? 'Aksi gagal.'),
            },
        );
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            {workState === 'todo' && <Button onClick={() => call('tasks.start')}>Mulai</Button>}

            {workState === 'dikerjakan-aktif' && (
                <>
                    <Button variant="outline" onClick={() => call('tasks.hold')}>
                        Jeda
                    </Button>
                    <Button onClick={() => call('tasks.submit')}>Submit</Button>
                </>
            )}

            {workState === 'dikerjakan-jeda' && (
                <>
                    <Button variant="outline" onClick={() => call('tasks.resume')}>
                        Lanjut
                    </Button>
                    <Button onClick={() => call('tasks.submit')}>Submit</Button>
                </>
            )}
        </div>
    );
}

export type { WorkState };

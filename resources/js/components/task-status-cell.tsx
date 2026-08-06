// ==========================================================
// MODUL       : task-status-cell
// KLASIFIKASI : UI
// TUJUAN      : Kontrol ubah status task — dipakai BERSAMA oleh tasks/index.tsx
//               (Hari-4/5) dan my-tasks/index.tsx (Hari-5 §D4), supaya keduanya
//               lewat endpoint & aturan yang SAMA PERSIS (F-45/F-28), bukan dua
//               implementasi yang bisa drift.
// DIPANGGIL   : tasks/index.tsx, my-tasks/index.tsx, tasks/show.tsx
// MEMANGGIL   : route('tasks.status'/'tasks.approve'/'tasks.reject')
// DATA MASUK  : projectId, task {id, task_status, assignees}, statuses[] project ybs,
//               canManageTask/canApprove (F-90 — dari auth.permissions caller, BUKAN
//               satu boolean isAdmin: task.manage & task.approve permission BEDA,
//               role custom bisa punya salah satu tanpa yang lain)
// DATA KELUAR : PATCH status/approve/reject -> TaskTransitionService (server)
// RISIKO      : Opsi target di dropdown cuma HINT UI (F-45 maju+1/mundur bebas,
//               is_completed selalu dikecualikan) — validasi ASLI tetap di server
//               (TaskTransitionService), jadi salah filter di sini paling buruk
//               cuma bikin opsi hilang dari dropdown, BUKAN lubang keamanan.
// ==========================================================

import { Button } from '@/components/ui/button';
import { confirmAction, promptInput, showError } from '@/lib/swal';
import { router } from '@inertiajs/react';

export interface TaskStatusOption {
    id: number;
    name: string;
    color: string;
    position: number;
    is_work_state: boolean;
    is_review: boolean;
    is_completed: boolean;
}

interface TaskStatusCellProps {
    projectId: number;
    task: {
        id: number;
        title: string;
        task_status: TaskStatusOption;
        assignees: { id: number; name: string }[];
    };
    statuses: TaskStatusOption[];
    currentUserId: number;
    canManageTask: boolean;
    canApprove: boolean;
}

export default function TaskStatusCell({ projectId, task, statuses, currentUserId, canManageTask, canApprove }: TaskStatusCellProps) {
    const isAssignee = task.assignees.some((a) => a.id === currentUserId);
    const canAct = canManageTask || isAssignee;

    const changeStatus = (statusId: number) => {
        router.patch(
            route('tasks.status', [projectId, task.id]),
            { task_status_id: statusId },
            {
                preserveScroll: true,
                onError: (errors) => {
                    if (errors.task_status_id) showError(errors.task_status_id);
                },
            },
        );
    };

    const approve = async () => {
        const rating = await promptInput('Quality rating (1-5)?', {
            title: 'Approve task',
            inputType: 'number',
            placeholder: '1-5',
            validator: (value) => {
                const n = Number(value);
                if (!value || !Number.isInteger(n) || n < 1 || n > 5) return 'Isi angka 1-5.';
                return null;
            },
        });
        if (!rating) return;

        router.patch(
            route('tasks.approve', [projectId, task.id]),
            { quality_rating: Number(rating) },
            { preserveScroll: true, onError: (errors) => showError(errors.quality_rating ?? 'Approve gagal.') },
        );
    };

    const reject = async () => {
        if (!(await confirmAction(`Tolak task "${task.title}"? Task kembali ke status kerja, rejection_count bertambah.`, { danger: true }))) return;
        router.patch(route('tasks.reject', [projectId, task.id]), {}, { preserveScroll: true });
    };

    if (task.task_status.is_review) {
        if (!canApprove) {
            return <span className="text-xs text-muted-foreground">Menunggu review</span>;
        }

        return (
            <div className="flex flex-wrap items-center gap-2">
                <Button type="button" size="sm" onClick={approve}>
                    Approve
                </Button>
                <Button type="button" size="sm" variant="outline" onClick={reject}>
                    Reject
                </Button>
            </div>
        );
    }

    // BUSINESS RULE F-45: opsi target maju cuma position+1, mundur bebas ke posisi
    // lebih rendah. is_completed SELALU dikecualikan — cuma bisa lewat approve (F-28).
    const options = statuses.filter(
        (s) =>
            s.id !== task.task_status.id &&
            !s.is_completed &&
            (s.position < task.task_status.position || s.position === task.task_status.position + 1),
    );

    if (!canAct || options.length === 0) {
        return null;
    }

    return (
        <select
            className="h-8 rounded-md border border-input bg-background px-2 text-xs"
            value=""
            onChange={(e) => {
                if (e.target.value) changeStatus(Number(e.target.value));
            }}
        >
            <option value="">Ubah status...</option>
            {options.map((s) => (
                <option key={s.id} value={s.id}>
                    {s.name}
                </option>
            ))}
        </select>
    );
}

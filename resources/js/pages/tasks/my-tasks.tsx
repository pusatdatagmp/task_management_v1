// ==========================================================
// MODUL       : tasks/my-tasks
// KLASIFIKASI : UI
// TUJUAN      : Halaman kerja utama member (Hari-5 §D) — task lintas project yang
//               di-assign ke user login, dikelompokkan Terlambat/Hari ini/Minggu
//               ini/Nanti (D2). Task selesai TIDAK ditampilkan (D6 — ini daftar
//               kerja aktif, bukan riwayat/dashboard).
// DIPANGGIL   : TaskController::myTasks()
// MEMANGGIL   : TaskStatusCell (ubah status lewat jalur F-45/F-28 yang sama)
// DATA MASUK  : groups {overdue, today, this_week, later} — masing-masing array task
// DATA KELUAR : Aksi ubah status (lihat TaskStatusCell)
// RISIKO      : -
// ==========================================================

import TaskLiveCounter, { type LiveCounterData } from '@/components/task-live-counter';
import TaskStatusCell, { type TaskStatusOption } from '@/components/task-status-cell';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';

interface MyTaskRow {
    id: number;
    title: string;
    priority: string;
    due_date: string;
    task_status: TaskStatusOption;
    assignees: { id: number; name: string }[];
    project: { id: number; name: string; task_statuses: TaskStatusOption[] };
    live_counter: LiveCounterData | null;
    // Revisi 2026-08-06 item 1: persentase progress (F-123 basis).
    progress_percent: number;
    checklist_items_count: number;
}

interface MyTasksProps {
    groups: {
        overdue: MyTaskRow[];
        today: MyTaskRow[];
        this_week: MyTaskRow[];
        later: MyTaskRow[];
    };
}

const SECTIONS: { key: keyof MyTasksProps['groups']; label: string; emptyText: string }[] = [
    { key: 'overdue', label: 'Terlambat', emptyText: 'Tidak ada task terlambat.' },
    { key: 'today', label: 'Hari ini', emptyText: 'Tidak ada task jatuh tempo hari ini.' },
    { key: 'this_week', label: 'Minggu ini', emptyText: 'Tidak ada task jatuh tempo minggu ini.' },
    { key: 'later', label: 'Nanti', emptyText: 'Tidak ada task untuk nanti.' },
];

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Task Saya', href: '/my-tasks' }];

export default function MyTasks({ groups }: MyTasksProps) {
    const { auth } = usePage<SharedData>().props;
    const can = (permission: string) => auth.permissions.includes(permission);
    const totalCount = SECTIONS.reduce((sum, s) => sum + groups[s.key].length, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Task Saya" />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold">Task Saya</h1>

                {totalCount === 0 && (
                    <p className="rounded-lg border p-6 text-center text-muted-foreground">
                        Tidak ada task aktif yang di-assign ke kamu. Kerja bagus, atau saatnya tanya admin.
                    </p>
                )}

                {SECTIONS.map(
                    (section) =>
                        groups[section.key].length > 0 && (
                            <div key={section.key} className="flex flex-col gap-2">
                                <h2 className="text-sm font-semibold text-muted-foreground">
                                    {section.label} ({groups[section.key].length})
                                </h2>
                                <div className="overflow-x-auto rounded-lg border">
                                    <table className="w-full text-left text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/50 text-muted-foreground">
                                                <th className="p-3">Judul</th>
                                                <th className="p-3">Project</th>
                                                <th className="p-3">Prioritas</th>
                                                <th className="p-3">Status</th>
                                                <th className="p-3">Progress</th>
                                                <th className="p-3">Due date</th>
                                                <th className="p-3">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {groups[section.key].map((task) => (
                                                <tr key={task.id} className="border-b last:border-0">
                                                    <td className="p-3 font-medium">
                                                        <div className="flex flex-col gap-1">
                                                            <span>{task.title}</span>
                                                            {/* B2: counter kecil di baris — badge default (bukan varian dot). */}
                                                            <TaskLiveCounter isWorkState={task.task_status.is_work_state} liveCounter={task.live_counter} />
                                                        </div>
                                                    </td>
                                                    <td className="p-3">{task.project.name}</td>
                                                    <td className="p-3 capitalize">{task.priority}</td>
                                                    <td className="p-3">
                                                        <Badge
                                                            style={{
                                                                backgroundColor: task.task_status.color,
                                                                color: '#fff',
                                                                borderColor: 'transparent',
                                                            }}
                                                        >
                                                            {task.task_status.name}
                                                        </Badge>
                                                    </td>
                                                    <td className="p-3">
                                                        {task.checklist_items_count > 0 ? (
                                                            <Badge variant="outline">{task.progress_percent}%</Badge>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground">-</span>
                                                        )}
                                                    </td>
                                                    <td className="p-3">{new Date(task.due_date).toLocaleString('id-ID')}</td>
                                                    <td className="p-3">
                                                        <TaskStatusCell
                                                            projectId={task.project.id}
                                                            task={task}
                                                            statuses={task.project.task_statuses}
                                                            currentUserId={auth.user.id}
                                                            canManageTask={can('task.manage')}
                                                            canApprove={can('task.approve')}
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ),
                )}
            </div>
        </AppLayout>
    );
}

// ==========================================================
// MODUL       : tasks/all
// KLASIFIKASI : UI
// TUJUAN      : Halaman "Semua Tugas" (F-140/F-144, v1.2 H7b) — List FLAT lintas
//               SEMUA project (admin oversight, project.viewAll). Filter status pakai
//               FLAG (todo/in_progress/review/completed), bukan task_status_id mentah
//               (F-44) — status TIDAK seragam antar project. Sort/filter prioritas
//               pakai priority_quadrant (F-139), enum priority lama disembunyikan.
// DIPANGGIL   : TaskController::all()
// MEMANGGIL   : route('tasks.board', project_id) untuk toggle Kanban — REUSE Board
//               v1.0 apa adanya (F-109), TaskStatusCell (F-45/F-28, sama my-tasks.tsx)
// DATA MASUK  : tasks (paginator), projects[], members[], filters
// DATA KELUAR : router.get (filter/sort, URL C6), navigasi ke Board bila project_id dipilih
// RISIKO      : SUMBER — toggle "Board View" HANYA aktif kalau filters.project_id
//               terisi (keputusan Boss): task_statuses per-project, kolom Kanban lintas
//               project tidak punya arti tunggal. Jangan tampilkan tombol ini tanpa
//               project_id — bisa mengarah ke route('tasks.board', undefined).
// ==========================================================

import TaskLiveCounter, { type LiveCounterData } from '@/components/task-live-counter';
import TaskStatusCell, { type TaskStatusOption } from '@/components/task-status-cell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { PRIORITY_QUADRANT_COLOR, PRIORITY_QUADRANT_LABEL, type PriorityQuadrant } from '@/lib/priority-quadrant';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';

interface UserOption {
    id: number;
    name: string;
}

interface ProjectOption {
    id: number;
    name: string;
}

interface TaskRow {
    id: number;
    title: string;
    task_type: 'daily' | 'weekly' | 'monthly' | 'tentative' | 'project';
    priority_quadrant: PriorityQuadrant | null;
    due_date: string;
    points: number;
    task_status: TaskStatusOption;
    assignees: UserOption[];
    project: { id: number; name: string; task_statuses: TaskStatusOption[] };
    parent: { id: number; title: string } | null;
    live_counter: LiveCounterData | null;
}

interface PaginatedTasks {
    data: TaskRow[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

type StatusFlag = 'todo' | 'in_progress' | 'review' | 'completed';

interface Filters {
    project_id: number | null;
    status_flag: StatusFlag[];
    assignee: number[];
    task_type: TaskRow['task_type'][];
    priority_quadrant: PriorityQuadrant[];
    due: 'today' | 'this_week' | 'overdue' | 'all';
    sort: 'due_date' | 'priority_quadrant' | 'points' | 'created_at';
    direction: 'asc' | 'desc';
}

interface AllTasksProps {
    tasks: PaginatedTasks;
    projects: ProjectOption[];
    members: UserOption[];
    filters: Filters;
}

const STATUS_FLAGS: { value: StatusFlag; label: string }[] = [
    { value: 'todo', label: 'Belum dikerjakan' },
    { value: 'in_progress', label: 'Dikerjakan' },
    { value: 'review', label: 'Review' },
    { value: 'completed', label: 'Selesai' },
];
const TASK_TYPES: { value: TaskRow['task_type']; label: string }[] = [
    { value: 'daily', label: 'Harian' },
    { value: 'weekly', label: 'Mingguan' },
    { value: 'monthly', label: 'Bulanan' },
    { value: 'tentative', label: 'Tentatif' },
    { value: 'project', label: 'Proyek' },
];
const QUADRANTS: PriorityQuadrant[] = ['p1', 'p2', 'p3', 'p4'];
const DUE_OPTIONS: { value: Filters['due']; label: string }[] = [
    { value: 'all', label: 'Semua' },
    { value: 'today', label: 'Hari ini' },
    { value: 'this_week', label: 'Minggu ini' },
    { value: 'overdue', label: 'Terlambat' },
];
const SORT_OPTIONS: { value: Filters['sort']; label: string }[] = [
    { value: 'due_date', label: 'Due date' },
    { value: 'priority_quadrant', label: 'Prioritas (Quadrant)' },
    { value: 'points', label: 'Poin' },
    { value: 'created_at', label: 'Dibuat' },
];

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Semua Tugas', href: '/tasks' }];

export default function AllTasks({ tasks, projects, members, filters }: AllTasksProps) {
    const { auth } = usePage<SharedData>().props;
    const can = (permission: string) => auth.permissions.includes(permission);

    // SUMBER: kirim OBJEK BARU (bukan spread URL saat ini) supaya 'page' reset ke 1
    // tiap filter berubah — pola sama tasks/index.tsx (C6).
    const applyFilters = (overrides: Partial<Filters>) => {
        router.get(route('tasks.all'), { ...filters, ...overrides }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const toggleStatusFlag = (value: StatusFlag) => {
        applyFilters({ status_flag: filters.status_flag.includes(value) ? filters.status_flag.filter((v) => v !== value) : [...filters.status_flag, value] });
    };

    const toggleAssignee = (id: number) => {
        applyFilters({ assignee: filters.assignee.includes(id) ? filters.assignee.filter((v) => v !== id) : [...filters.assignee, id] });
    };

    const toggleTaskType = (value: TaskRow['task_type']) => {
        applyFilters({ task_type: filters.task_type.includes(value) ? filters.task_type.filter((v) => v !== value) : [...filters.task_type, value] });
    };

    const toggleQuadrant = (value: PriorityQuadrant) => {
        applyFilters({
            priority_quadrant: filters.priority_quadrant.includes(value)
                ? filters.priority_quadrant.filter((v) => v !== value)
                : [...filters.priority_quadrant, value],
        });
    };

    const resetFilters = () => {
        router.get(
            route('tasks.all'),
            { project_id: null, status_flag: [], assignee: [], task_type: [], priority_quadrant: [], due: 'all', sort: 'due_date', direction: 'asc' },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const hasActiveFilter =
        !!filters.project_id ||
        filters.status_flag.length > 0 ||
        filters.assignee.length > 0 ||
        filters.task_type.length > 0 ||
        filters.priority_quadrant.length > 0 ||
        filters.due !== 'all';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Semua Tugas" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Semua Tugas</h1>
                    <div className="flex gap-2">
                        {/* F-109/keputusan Boss: Kanban cuma valid kalau 1 project dipilih —
                            task_statuses per-project, kolom lintas-project tidak punya arti tunggal. */}
                        {filters.project_id ? (
                            <Button variant="outline" asChild>
                                <Link href={route('tasks.board', filters.project_id)}>Board View</Link>
                            </Button>
                        ) : (
                            <span className="self-center text-xs text-muted-foreground">Pilih 1 project untuk lihat Board View</span>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap items-start gap-6 rounded-lg border p-4 text-sm">
                    <div className="flex flex-col gap-1">
                        <span className="font-medium">Project</span>
                        <select
                            className="h-8 rounded-md border border-input bg-background px-2"
                            value={filters.project_id ?? ''}
                            onChange={(e) => applyFilters({ project_id: e.target.value ? Number(e.target.value) : null })}
                        >
                            <option value="">Semua Project</option>
                            {projects.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex flex-col gap-1">
                        <span className="font-medium">Status</span>
                        {STATUS_FLAGS.map((s) => (
                            <label key={s.value} className="flex items-center gap-2">
                                <input type="checkbox" checked={filters.status_flag.includes(s.value)} onChange={() => toggleStatusFlag(s.value)} />
                                {s.label}
                            </label>
                        ))}
                    </div>

                    <div className="flex flex-col gap-1">
                        <span className="font-medium">User/Tim</span>
                        {members.map((m) => (
                            <label key={m.id} className="flex items-center gap-2">
                                <input type="checkbox" checked={filters.assignee.includes(m.id)} onChange={() => toggleAssignee(m.id)} />
                                {m.name}
                            </label>
                        ))}
                    </div>

                    <div className="flex flex-col gap-1">
                        <span className="font-medium">Kategori</span>
                        {TASK_TYPES.map((t) => (
                            <label key={t.value} className="flex items-center gap-2">
                                <input type="checkbox" checked={filters.task_type.includes(t.value)} onChange={() => toggleTaskType(t.value)} />
                                {t.label}
                            </label>
                        ))}
                    </div>

                    <div className="flex flex-col gap-1">
                        <span className="font-medium">Prioritas (Eisenhower)</span>
                        {QUADRANTS.map((q) => (
                            <label key={q} className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={filters.priority_quadrant.includes(q)}
                                    onChange={() => toggleQuadrant(q)}
                                />
                                {PRIORITY_QUADRANT_LABEL[q]}
                            </label>
                        ))}
                    </div>

                    <div className="flex flex-col gap-1">
                        <span className="font-medium">Due date</span>
                        {DUE_OPTIONS.map((opt) => (
                            <label key={opt.value} className="flex items-center gap-2">
                                <input type="radio" name="due" checked={filters.due === opt.value} onChange={() => applyFilters({ due: opt.value })} />
                                {opt.label}
                            </label>
                        ))}
                    </div>

                    <div className="flex flex-col gap-1">
                        <span className="font-medium">Urutkan</span>
                        <select
                            className="h-8 rounded-md border border-input bg-background px-2"
                            value={filters.sort}
                            onChange={(e) => applyFilters({ sort: e.target.value as Filters['sort'] })}
                        >
                            {SORT_OPTIONS.map((opt) => (
                                <option key={opt.value} value={opt.value}>
                                    {opt.label}
                                </option>
                            ))}
                        </select>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => applyFilters({ direction: filters.direction === 'asc' ? 'desc' : 'asc' })}
                        >
                            {filters.direction === 'asc' ? 'Naik ↑' : 'Turun ↓'}
                        </Button>
                    </div>

                    {hasActiveFilter && (
                        <Button type="button" variant="ghost" size="sm" className="self-end" onClick={resetFilters}>
                            Reset filter
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-muted-foreground">
                                <th className="p-3">Judul</th>
                                <th className="p-3">Project</th>
                                <th className="p-3">Prioritas</th>
                                <th className="p-3">Status</th>
                                <th className="p-3">Assignee</th>
                                <th className="p-3">Due date</th>
                                <th className="p-3">Poin</th>
                                <th className="p-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tasks.data.map((task, index) => (
                                <motion.tr
                                    key={task.id}
                                    className="border-b last:border-0"
                                    initial={{ opacity: 0, y: 6 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.15, delay: Math.min(index, 10) * 0.02 }}
                                >
                                    <td className="p-3">
                                        <div className="flex items-center gap-2">
                                            <Link href={route('tasks.show', [task.project.id, task.id])} className="font-medium hover:underline">
                                                {task.title}
                                            </Link>
                                            <TaskLiveCounter isWorkState={task.task_status.is_work_state} liveCounter={task.live_counter} variant="dot" />
                                        </div>
                                        {task.parent && <div className="text-xs text-muted-foreground">Subtask dari: {task.parent.title}</div>}
                                    </td>
                                    <td className="p-3">{task.project.name}</td>
                                    <td className="p-3">
                                        {task.priority_quadrant ? (
                                            <Badge
                                                style={{
                                                    backgroundColor: PRIORITY_QUADRANT_COLOR[task.priority_quadrant],
                                                    color: '#fff',
                                                    borderColor: 'transparent',
                                                }}
                                            >
                                                {PRIORITY_QUADRANT_LABEL[task.priority_quadrant]}
                                            </Badge>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">Belum diklasifikasi</span>
                                        )}
                                    </td>
                                    <td className="p-3">
                                        <Badge style={{ backgroundColor: task.task_status.color, color: '#fff', borderColor: 'transparent' }}>
                                            {task.task_status.name}
                                        </Badge>
                                    </td>
                                    <td className="p-3">{task.assignees.map((a) => a.name).join(', ') || '-'}</td>
                                    <td className="p-3">{new Date(task.due_date).toLocaleString('id-ID')}</td>
                                    <td className="p-3">{task.points}</td>
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
                                </motion.tr>
                            ))}

                            {tasks.data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="p-8 text-center">
                                        <p className="text-muted-foreground">Tidak ada task yang cocok dengan filter ini.</p>
                                        {hasActiveFilter && (
                                            <Button type="button" variant="outline" size="sm" className="mt-2" onClick={resetFilters}>
                                                Reset filter
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {tasks.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1">
                        {tasks.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={`rounded-md border px-3 py-1 text-sm ${
                                    link.active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'
                                } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

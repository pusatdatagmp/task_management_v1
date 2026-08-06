// ==========================================================
// MODUL       : tasks/index
// KLASIFIKASI : UI
// TUJUAN      : Daftar task per project — filter/sort SERVER-SIDE (Hari-5 §C, C5).
//               List FLAT (subtask ditandai kolom "Parent", bukan indentasi) —
//               indentasi tidak survive di bawah sort/filter/pagination sembarangan.
// DIPANGGIL   : TaskController::index()
// MEMANGGIL   : route('tasks.create'/'tasks.edit'/'tasks.destroy'/'tasks.board'
//               untuk toggle Board View, v1.0 H1), TaskStatusCell
// DATA MASUK  : project, tasks (paginator Laravel), statuses[], members[], filters
// DATA KELUAR : router.get (filter/sort, query string ikut URL — C6), navigasi CRUD
// RISIKO      : SUMBER : filter dikirim FRESH (bukan spread dari URL saat ini) tiap
//               kali applyFilters() dipanggil, supaya 'page' otomatis reset ke 1
//               saat filter berubah — tanpa ini, ganti filter di halaman 3 bisa
//               menampilkan "tidak ada hasil" walau datanya ada di halaman 1.
// ==========================================================

import TaskLiveCounter, { type LiveCounterData } from '@/components/task-live-counter';
import TaskStatusCell, { type TaskStatusOption } from '@/components/task-status-cell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { confirmAction } from '@/lib/swal';
import { PRIORITY_QUADRANT_COLOR, PRIORITY_QUADRANT_LABEL, type PriorityQuadrant } from '@/lib/priority-quadrant';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';

interface UserOption {
    id: number;
    name: string;
}

interface TaskRow {
    id: number;
    title: string;
    task_type: string;
    priority: string;
    priority_quadrant: PriorityQuadrant | null;
    due_date: string;
    estimated_minutes: number;
    points: number;
    task_status: TaskStatusOption;
    assignees: UserOption[];
    parent: { id: number; title: string } | null;
    live_counter: LiveCounterData | null;
    // Revisi 2026-08-06 item 1: persentase progress (F-123 basis, freeze saat Selesai).
    progress_percent: number;
    checklist_items_count: number;
}

interface PaginatedTasks {
    data: TaskRow[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Filters {
    status: number[];
    assignee: number[];
    priority: string[];
    due: 'today' | 'this_week' | 'overdue' | 'all';
    sort: 'due_date' | 'priority' | 'points' | 'created_at';
    direction: 'asc' | 'desc';
}

interface TasksIndexProps {
    project: { id: number; name: string };
    tasks: PaginatedTasks;
    statuses: TaskStatusOption[];
    members: UserOption[];
    filters: Filters;
}

const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
const DUE_OPTIONS: { value: Filters['due']; label: string }[] = [
    { value: 'all', label: 'Semua' },
    { value: 'today', label: 'Hari ini' },
    { value: 'this_week', label: 'Minggu ini' },
    { value: 'overdue', label: 'Terlambat' },
];
const SORT_OPTIONS: { value: Filters['sort']; label: string }[] = [
    { value: 'due_date', label: 'Due date' },
    { value: 'priority', label: 'Prioritas' },
    { value: 'points', label: 'Poin' },
    { value: 'created_at', label: 'Dibuat' },
];

export default function TasksIndex({ project, tasks, statuses, members, filters }: TasksIndexProps) {
    const { auth } = usePage<SharedData>().props;
    const can = (permission: string) => auth.permissions.includes(permission);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Project', href: '/projects' },
        { title: project.name, href: route('projects.edit', project.id) },
        { title: 'Task', href: route('tasks.index', project.id) },
    ];

    // SUMBER: selalu kirim OBJEK BARU (bukan URLSearchParams saat ini) supaya
    // 'page' tidak ikut terbawa saat filter berubah (C6 — URL tetap cerminan
    // filter aktif, tapi ganti filter = mulai dari halaman 1 lagi).
    const applyFilters = (overrides: Partial<Filters>) => {
        router.get(route('tasks.index', project.id), { ...filters, ...overrides }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const toggleArrayFilter = (key: 'status' | 'assignee', id: number) => {
        const current = filters[key];
        applyFilters({ [key]: current.includes(id) ? current.filter((v) => v !== id) : [...current, id] });
    };

    const togglePriority = (value: string) => {
        applyFilters({ priority: filters.priority.includes(value) ? filters.priority.filter((v) => v !== value) : [...filters.priority, value] });
    };

    const resetFilters = () => {
        router.get(
            route('tasks.index', project.id),
            { status: [], assignee: [], priority: [], due: 'all', sort: 'due_date', direction: 'asc' },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const hasActiveFilter = filters.status.length > 0 || filters.assignee.length > 0 || filters.priority.length > 0 || filters.due !== 'all';

    const destroy = async (task: TaskRow) => {
        if (!(await confirmAction(`Hapus task "${task.title}"?`, { danger: true }))) return;
        router.delete(route('tasks.destroy', [project.id, task.id]), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Task — ${project.name}`} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Task — {project.name}</h1>
                    <div className="flex gap-2">
                        {/* B3 (v1.0 H1): toggle List <-> Board untuk project yang sama. */}
                        <Button variant="outline" asChild>
                            <Link href={route('tasks.board', project.id)}>Board View</Link>
                        </Button>
                        {can('task.manage') && (
                            <Button asChild>
                                <Link href={route('tasks.create', project.id)}>Task Baru</Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap items-start gap-6 rounded-lg border p-4 text-sm">
                    <div className="flex flex-col gap-1">
                        <span className="font-medium">Status</span>
                        {statuses.map((s) => (
                            <label key={s.id} className="flex items-center gap-2">
                                <input type="checkbox" checked={filters.status.includes(s.id)} onChange={() => toggleArrayFilter('status', s.id)} />
                                {s.name}
                            </label>
                        ))}
                    </div>

                    <div className="flex flex-col gap-1">
                        <span className="font-medium">Assignee</span>
                        {members.map((m) => (
                            <label key={m.id} className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={filters.assignee.includes(m.id)}
                                    onChange={() => toggleArrayFilter('assignee', m.id)}
                                />
                                {m.name}
                            </label>
                        ))}
                        {members.length === 0 && <span className="text-muted-foreground">-</span>}
                    </div>

                    <div className="flex flex-col gap-1">
                        <span className="font-medium">Prioritas</span>
                        {PRIORITIES.map((p) => (
                            <label key={p} className="flex items-center gap-2 capitalize">
                                <input type="checkbox" checked={filters.priority.includes(p)} onChange={() => togglePriority(p)} />
                                {p}
                            </label>
                        ))}
                    </div>

                    <div className="flex flex-col gap-1">
                        <span className="font-medium">Due date</span>
                        {DUE_OPTIONS.map((opt) => (
                            <label key={opt.value} className="flex items-center gap-2">
                                <input
                                    type="radio"
                                    name="due"
                                    checked={filters.due === opt.value}
                                    onChange={() => applyFilters({ due: opt.value })}
                                />
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
                                <th className="p-3">Prioritas</th>
                                <th className="p-3">Status</th>
                                <th className="p-3">Progress</th>
                                <th className="p-3">Assignee</th>
                                <th className="p-3">Due date</th>
                                <th className="p-3">Poin</th>
                                <th className="p-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tasks.data.map((task) => (
                                <tr key={task.id} className="border-b last:border-0">
                                    <td className="p-3">
                                        <div className="flex items-center gap-2">
                                            <Link href={route('tasks.show', [project.id, task.id])} className="font-medium hover:underline">
                                                {task.title}
                                            </Link>
                                            {/* B3: indikator ringkas (dot + waktu). */}
                                            <TaskLiveCounter isWorkState={task.task_status.is_work_state} liveCounter={task.live_counter} variant="dot" />
                                        </div>
                                        {task.parent && <div className="text-xs text-muted-foreground">Subtask dari: {task.parent.title}</div>}
                                    </td>
                                    <td className="p-3">
                                        {/* F-122/F-126: badge Eisenhower gantikan tampilan enum priority lama. */}
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
                                    <td className="p-3">
                                        {/* Revisi 2026-08-06 item 1: nol badge kalau checklist kosong DAN belum
                                            selesai (nol indikator granular, bukan "0%" membingungkan). */}
                                        {task.checklist_items_count > 0 || task.task_status.is_completed ? (
                                            <Badge variant="outline">{task.progress_percent}%</Badge>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">-</span>
                                        )}
                                    </td>
                                    <td className="p-3">{task.assignees.map((a) => a.name).join(', ') || '-'}</td>
                                    <td className="p-3">{new Date(task.due_date).toLocaleString('id-ID')}</td>
                                    <td className="p-3">{task.points}</td>
                                    <td className="p-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <TaskStatusCell
                                                projectId={project.id}
                                                task={task}
                                                statuses={statuses}
                                                currentUserId={auth.user.id}
                                                canManageTask={can('task.manage')}
                                                canApprove={can('task.approve')}
                                            />
                                            {can('task.manage') && (
                                                <>
                                                    <Button type="button" variant="outline" size="sm" asChild>
                                                        <Link href={route('tasks.edit', [project.id, task.id])}>Edit</Link>
                                                    </Button>
                                                    <Button type="button" variant="outline" size="sm" onClick={() => destroy(task)}>
                                                        Hapus
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
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

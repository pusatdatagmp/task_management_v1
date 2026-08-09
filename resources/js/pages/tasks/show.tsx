// ==========================================================
// MODUL       : tasks/show
// KLASIFIKASI : UI
// TUJUAN      : Halaman detail task (F-82) — satu-satunya tempat member membaca
//               description rich text (F-30). List View tidak punya kolom ini, dan
//               member tidak boleh masuk form edit (F-29).
// DIPANGGIL   : TaskController::show()
// MEMANGGIL   : TaskStatusCell (ubah status lewat jalur F-45/F-28 yang sama dipakai
//               tasks/index & my-tasks), TaskWorkActions (H7/F-132/F-138 — tombol
//               Mulai/Jeda/Lanjut/Submit, assignee-only), TaskAttachments (upload/
//               download/hapus lampiran output, v0.8 H5/F-49), TaskComments
//               (diskusi+mention, v1.0 H3/F-113), Card "Riwayat" (activity_logs
//               read-only, v1.0 H4/F-95/F-106 — label SUDAH manusiawi dari server,
//               nol logika di sini), route('tasks.edit'/'tasks.destroy'/'tasks.show')
// DATA MASUK  : project, task (description_html SUDAH DISANITASI di server, F-82 A3;
//               work_state H7 5-nilai task-wide dari Task::computeWorkState()),
//               statuses[]. F-90: TIDAK ADA lagi prop 'isAdmin' — permission dibaca
//               dari auth.permissions (shared global, HandleInertiaRequests).
// DATA KELUAR : navigasi edit/hapus (task.manage), aksi ubah status (TaskStatusCell),
//               aksi Mulai/Jeda/Lanjut/Submit (TaskWorkActions, H7)
// RISIKO      : description_html dirender via dangerouslySetInnerHTML — AMAN karena
//               backend (TaskController::show()) sudah sanitasi pakai Symfony
//               HtmlSanitizer sebelum sampai ke props ini. JANGAN render field HTML
//               lain lewat pola yang sama tanpa sanitasi server yang setara.
// ==========================================================

import TaskAttachments from '@/components/task-attachments';
import TaskChecklist from '@/components/task-checklist';
import TaskComments from '@/components/task-comments';
import TaskLiveCounter, { type LiveCounterData } from '@/components/task-live-counter';
import TaskStatusCell, { type TaskStatusOption } from '@/components/task-status-cell';
import TaskWorkActions, { type WorkState } from '@/components/task-work-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { PRIORITY_QUADRANT_COLOR, PRIORITY_QUADRANT_LABEL, type PriorityQuadrant } from '@/lib/priority-quadrant';
import { confirmAction } from '@/lib/swal';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';

interface UserOption {
    id: number;
    name: string;
}

interface TaskLink {
    id: number;
    title: string;
    task_status: TaskStatusOption;
}

interface AttachmentData {
    id: number;
    file_name: string;
    file_size: number;
    uploaded_by: UserOption;
    created_at: string;
}

interface CommentData {
    id: number;
    body: string | null;
    user: UserOption;
    created_at: string;
    is_edited: boolean;
    is_deleted: boolean;
    is_mine: boolean;
}

interface ActivityLogRow {
    id: number;
    message: string;
    created_at: string;
}

interface ChecklistItemData {
    id: number;
    text: string;
    is_done: boolean;
}

interface TaskDetail {
    id: number;
    title: string;
    task_type: string;
    priority: string;
    priority_quadrant: PriorityQuadrant | null;
    due_date: string;
    points: number;
    estimated_minutes: number;
    actual_minutes: number | null;
    quality_rating: number | null;
    rejection_count: number;
    approved_at: string | null;
    original_due_date: string | null;
    description_html: string | null;
    task_status: TaskStatusOption;
    // H7/F-132/F-138: state 5-nilai task-wide, dihitung Task::computeWorkState() server.
    work_state: WorkState;
    assignees: UserOption[];
    parent: TaskLink | null;
    children: TaskLink[];
    attachments: AttachmentData[];
    checklist_items: ChecklistItemData[];
    comments: CommentData[];
    activity_logs: ActivityLogRow[];
    live_counter: LiveCounterData | null;
    // Revisi 2026-08-06 item 1: persentase progress (F-123 basis) -- SELALU 100
    // kalau task_status.is_completed (freeze, Task::progressPercent()).
    progress_percent: number;
}

interface TaskShowProps {
    project: { id: number; name: string };
    task: TaskDetail;
    statuses: TaskStatusOption[];
    projectMembers: UserOption[];
}

function timeAgo(isoDate: string): string {
    const diffMs = Date.now() - new Date(isoDate).getTime();
    const minutes = Math.floor(diffMs / 60000);

    if (minutes < 1) return 'baru saja';
    if (minutes < 60) return `${minutes} menit lalu`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} jam lalu`;
    const days = Math.floor(hours / 24);

    return `${days} hari lalu`;
}

export default function TaskShow({ project, task, statuses, projectMembers }: TaskShowProps) {
    const { auth } = usePage<SharedData>().props;
    const can = (permission: string) => auth.permissions.includes(permission);
    // H7/F-95: tombol Mulai/Jeda/Lanjut/Submit HANYA assignee -- dihitung dari
    // daftar assignee yang SUDAH dikirim server, nol permission baru (F-95).
    const isAssignee = task.assignees.some((a) => a.id === auth.user.id);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Project', href: '/projects' },
        { title: project.name, href: route('projects.edit', project.id) },
        { title: 'Task', href: route('tasks.index', project.id) },
        { title: task.title, href: '#' },
    ];

    const destroy = async () => {
        if (!(await confirmAction(`Hapus task "${task.title}"?`, { danger: true }))) return;
        router.delete(route('tasks.destroy', [project.id, task.id]));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${task.title} — ${project.name}`} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex flex-col gap-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-xl font-semibold">{task.title}</h1>
                            <Badge style={{ backgroundColor: task.task_status.color, color: '#fff', borderColor: 'transparent' }}>
                                {task.task_status.name}
                            </Badge>
                            {/* F-122/F-126: badge Eisenhower — nol tampil kalau belum diklasifikasi. */}
                            {task.priority_quadrant && (
                                <Badge
                                    style={{ backgroundColor: PRIORITY_QUADRANT_COLOR[task.priority_quadrant], color: '#fff', borderColor: 'transparent' }}
                                >
                                    {PRIORITY_QUADRANT_LABEL[task.priority_quadrant]}
                                </Badge>
                            )}
                            {/* Revisi 2026-08-06 item 1: persentase progress (F-123 basis, freeze
                                saat Selesai) -- checklist_items kosong DAN belum selesai = badge
                                tidak tampil (nol indikator granular, bukan 0% membingungkan). */}
                            {(task.checklist_items.length > 0 || task.task_status.is_completed) && (
                                <Badge variant="outline">{task.progress_percent}% selesai</Badge>
                            )}
                        </div>
                        {task.parent && (
                            <Link
                                href={route('tasks.show', [project.id, task.parent.id])}
                                className="text-sm text-muted-foreground hover:underline"
                            >
                                Subtask dari: {task.parent.title}
                            </Link>
                        )}
                        {/* B1: badge besar, tersembunyi otomatis kalau bukan is_work_state (F-44).
                            H7: isPaused dari work_state task-wide (F-138b/f), BUKAN per-user. */}
                        <TaskLiveCounter
                            isWorkState={task.task_status.is_work_state}
                            liveCounter={task.live_counter}
                            isPaused={task.work_state === 'dikerjakan-jeda'}
                        />
                    </div>

                    {can('task.manage') && (
                        <div className="flex gap-2">
                            <Button type="button" variant="outline" size="sm" asChild>
                                <Link href={route('tasks.edit', [project.id, task.id])}>Edit</Link>
                            </Button>
                            <Button type="button" variant="outline" size="sm" onClick={destroy}>
                                Hapus
                            </Button>
                        </div>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Deskripsi</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {task.description_html ? (
                                <div
                                    className="prose-sm max-w-none text-sm [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5"
                                    dangerouslySetInnerHTML={{ __html: task.description_html }}
                                />
                            ) : (
                                <p className="text-sm text-muted-foreground">Tidak ada deskripsi.</p>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Detail</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3 text-sm">
                                <div className="flex justify-between gap-2">
                                    <span className="text-muted-foreground">Assignee</span>
                                    <span className="text-right">{task.assignees.map((a) => a.name).join(', ') || '-'}</span>
                                </div>
                                <div className="flex justify-between gap-2">
                                    <span className="text-muted-foreground">Tipe</span>
                                    <span className="capitalize">{task.task_type}</span>
                                </div>
                                <div className="flex justify-between gap-2">
                                    <span className="text-muted-foreground">Due date</span>
                                    <span>{new Date(task.due_date).toLocaleString('id-ID')}</span>
                                </div>
                                {/* F-47 (v0.8 H6): tampil HANYA kalau task pernah diperpanjang —
                                    original_due_date = jangkar tenggat ASLI sebelum extension pertama. */}
                                {task.original_due_date && (
                                    <div className="flex justify-between gap-2">
                                        <span className="text-muted-foreground">Tenggat asli</span>
                                        <span className="text-right">
                                            {new Date(task.original_due_date).toLocaleString('id-ID')} (diperpanjang)
                                        </span>
                                    </div>
                                )}
                                <div className="flex justify-between gap-2">
                                    <span className="text-muted-foreground">Poin</span>
                                    <span>{task.points}</span>
                                </div>
                                <div className="flex justify-between gap-2">
                                    <span className="text-muted-foreground">Estimasi</span>
                                    <span>{task.estimated_minutes} menit</span>
                                </div>
                                {task.actual_minutes !== null && (
                                    <div className="flex justify-between gap-2">
                                        <span className="text-muted-foreground">Realisasi</span>
                                        <span>{task.actual_minutes} menit</span>
                                    </div>
                                )}
                                {task.quality_rating !== null && (
                                    <div className="flex justify-between gap-2">
                                        <span className="text-muted-foreground">Quality rating</span>
                                        <span>{task.quality_rating}/5</span>
                                    </div>
                                )}
                                {task.rejection_count > 0 && (
                                    <div className="flex justify-between gap-2">
                                        <span className="text-muted-foreground">Ditolak</span>
                                        <span>{task.rejection_count}x</span>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Status</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <TaskStatusCell
                                    projectId={project.id}
                                    task={task}
                                    statuses={statuses}
                                    currentUserId={auth.user.id}
                                    canManageTask={can('task.manage')}
                                    canApprove={can('task.approve')}
                                />
                                {/* H7/F-132/F-138: Mulai/Jeda/Lanjut/Submit -- assignee-only,
                                    tombol berbeda per work_state, nol render kalau bukan assignee
                                    atau status sudah review/selesai (komponen sendiri yang jaga). */}
                                <TaskWorkActions
                                    projectId={project.id}
                                    taskId={task.id}
                                    workState={task.work_state}
                                    isAssignee={isAssignee}
                                />
                            </CardContent>
                        </Card>

                        {/* F-123/F-127: gate ->review dicek SERVER (TaskTransitionService) —
                            komponen ini murni CRUD + progress, nol validasi gate di sini. */}
                        <TaskChecklist
                            projectId={project.id}
                            taskId={task.id}
                            items={task.checklist_items}
                            canManageTask={can('task.manage')}
                            isAssignee={task.assignees.some((a) => a.id === auth.user.id)}
                            isWorkState={task.task_status.is_work_state}
                        />

                        <TaskAttachments
                            projectId={project.id}
                            taskId={task.id}
                            attachments={task.attachments}
                            canManageTask={can('task.manage')}
                            isAssignee={task.assignees.some((a) => a.id === auth.user.id)}
                            isLocked={task.approved_at !== null}
                        />

                        {task.children.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Subtask ({task.children.length})</CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-2">
                                    {task.children.map((child) => (
                                        <Link
                                            key={child.id}
                                            href={route('tasks.show', [project.id, child.id])}
                                            className="flex items-center justify-between gap-2 rounded-md border p-2 text-sm hover:bg-accent"
                                        >
                                            <span>{child.title}</span>
                                            <Badge
                                                style={{ backgroundColor: child.task_status.color, color: '#fff', borderColor: 'transparent' }}
                                            >
                                                {child.task_status.name}
                                            </Badge>
                                        </Link>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        {/* v1.0 H4 (F-95/F-106): riwayat task ini saja, label SUDAH
                            manusiawi dari server (ActivityLogPresenter) — read-only,
                            tidak ada aksi apa pun di sini. */}
                        {task.activity_logs.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Riwayat</CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-2">
                                    {task.activity_logs.map((log) => (
                                        <div key={log.id} className="flex flex-col gap-0.5 border-b pb-2 text-sm last:border-0 last:pb-0">
                                            <span>{log.message}</span>
                                            <span className="text-xs text-muted-foreground">{timeAgo(log.created_at)}</span>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>

                <TaskComments projectId={project.id} taskId={task.id} comments={task.comments} projectMembers={projectMembers} />
            </div>
        </AppLayout>
    );
}

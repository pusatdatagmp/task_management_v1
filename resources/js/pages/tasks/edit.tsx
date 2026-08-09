// ==========================================================
// MODUL       : tasks/edit
// KLASIFIKASI : UI
// TUJUAN      : Form edit task (admin only, F-29). Field sama dengan create,
//               pre-filled dari data existing. Ubah status BUKAN di sini — itu di
//               tasks/index (E1/E2), supaya form ini murni CRUD field, bukan alur kerja.
//               BUG FIX (2026-08-08, permintaan Boss): task hasil recurring
//               (task_template_id terisi) task_type-nya daily/weekly/monthly --
//               dropdown ini cuma punya opsi tentative/project, jadi field ini
//               DIKUNCI jadi teks read-only untuk task recurring (bukan Select),
//               TIDAK ikut dikirim ke server. SEBELUM fix ini, submit edit task
//               recurring APA PUN (termasuk cuma ganti judul) SELALU gagal
//               validasi task_type -- Laravel menolak seluruh form sekaligus.
// DIPANGGIL   : TaskController::edit()
// MEMANGGIL   : route('tasks.update')
// DATA MASUK  : project, task (existing, termasuk task_template_id), assigneeIds,
//               members[], parentOptions[]
// DATA KELUAR : PUT form -> TaskController::update()
// RISIKO      : due_date dari server sudah WIB (trait F-72) — toLocalInput() cuma
//               memotong string ISO ke format datetime-local, TIDAK melakukan
//               konversi timezone apa pun (F-69: tidak ada konversi UTC di mana pun).
// ==========================================================

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { PRIORITY_QUADRANT_OPTIONS, type PriorityQuadrant } from '@/lib/priority-quadrant';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface UserOption {
    id: number;
    name: string;
}

interface ParentOption {
    id: number;
    title: string;
}

interface TaskData {
    id: number;
    title: string;
    description: string | null;
    task_type: string;
    task_template_id: number | null;
    priority_quadrant: PriorityQuadrant | null;
    estimated_minutes: number;
    points: number;
    due_date: string;
}

const TASK_TYPE_LABEL: Record<string, string> = {
    tentative: 'Tentative',
    project: 'Project',
    daily: 'Harian (berulang)',
    weekly: 'Mingguan (berulang)',
    monthly: 'Bulanan (berulang)',
};

interface TaskEditProps {
    project: { id: number; name: string };
    task: TaskData;
    assigneeIds: number[];
    members: UserOption[];
    parentOptions: ParentOption[];
}

function toLocalInput(iso: string): string {
    // SUMBER: iso sudah string WIB (trait SerializesDatesInAppTimezone, F-72) —
    // cukup potong ke 16 karakter "YYYY-MM-DDTHH:mm", JANGAN new Date().toISOString()
    // (itu akan konversi ke UTC lagi, F-69).
    return iso.slice(0, 16);
}

export default function TaskEdit({ project, task, assigneeIds, members, parentOptions }: TaskEditProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Project', href: '/projects' },
        { title: project.name, href: route('projects.edit', project.id) },
        { title: 'Task', href: route('tasks.index', project.id) },
        { title: `Edit ${task.title}`, href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm({
        title: task.title,
        description: task.description ?? '',
        task_type: task.task_type,
        // F-126: enum `priority` lama TIDAK dikirim dari form ini lagi.
        priority_quadrant: (task.priority_quadrant ?? '') as PriorityQuadrant | '',
        estimated_minutes: task.estimated_minutes,
        points: task.points,
        due_date: toLocalInput(task.due_date),
        assignees: assigneeIds,
        parent_task_id: '' as number | '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('tasks.update', [project.id, task.id]));
    };

    const toggleAssignee = (id: number, checked: boolean) => {
        setData('assignees', checked ? [...data.assignees, id] : data.assignees.filter((a) => a !== id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${task.title} — ${project.name}`} />

            <div className="p-4">
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Edit Task</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Judul</Label>
                                <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Deskripsi</Label>
                                <RichTextEditor id="description" value={data.description} onChange={(html) => setData('description', html)} />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="task_type">Tipe task</Label>
                                    {task.task_template_id ? (
                                        // BUG FIX (2026-08-08): task hasil recurring -- tipe dikunci dari
                                        // Template asalnya, tidak bisa diubah lewat form ini (lihat header).
                                        <div className="flex h-9 items-center rounded-md border bg-muted px-3 text-sm text-muted-foreground">
                                            {TASK_TYPE_LABEL[data.task_type] ?? data.task_type} (mengikuti Template)
                                        </div>
                                    ) : (
                                        <Select value={data.task_type} onValueChange={(value) => setData('task_type', value)}>
                                            <SelectTrigger id="task_type">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="tentative">Tentative</SelectItem>
                                                <SelectItem value="project">Project</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    )}
                                    <InputError message={errors.task_type} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="priority_quadrant">Prioritas (Eisenhower)</Label>
                                    <Select
                                        value={data.priority_quadrant || '__none'}
                                        onValueChange={(value) => setData('priority_quadrant', value === '__none' ? '' : (value as PriorityQuadrant))}
                                    >
                                        <SelectTrigger id="priority_quadrant">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__none">Belum diklasifikasi</SelectItem>
                                            {PRIORITY_QUADRANT_OPTIONS.map((opt) => (
                                                <SelectItem key={opt.value} value={opt.value}>
                                                    {opt.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.priority_quadrant} />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="estimated_minutes">Estimasi (menit)</Label>
                                    <Input
                                        id="estimated_minutes"
                                        type="number"
                                        min={1}
                                        value={data.estimated_minutes}
                                        onChange={(e) => setData('estimated_minutes', Number(e.target.value))}
                                        required
                                    />
                                    <InputError message={errors.estimated_minutes} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="points">Poin</Label>
                                    <Input
                                        id="points"
                                        type="number"
                                        min={0}
                                        value={data.points}
                                        onChange={(e) => setData('points', Number(e.target.value))}
                                        required
                                    />
                                    <InputError message={errors.points} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="due_date">Due date</Label>
                                <Input
                                    id="due_date"
                                    type="datetime-local"
                                    value={data.due_date}
                                    onChange={(e) => setData('due_date', e.target.value)}
                                    required
                                />
                                <InputError message={errors.due_date} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="parent_task_id">Parent task (opsional — subtask maks 1 level, F-20)</Label>
                                <Select
                                    value={data.parent_task_id ? String(data.parent_task_id) : '__none'}
                                    onValueChange={(value) => setData('parent_task_id', value === '__none' ? '' : Number(value))}
                                >
                                    <SelectTrigger id="parent_task_id">
                                        <SelectValue placeholder="Tidak ada (task utama)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none">Tidak ada (task utama)</SelectItem>
                                        {parentOptions.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>
                                                {p.title}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.parent_task_id} />
                            </div>

                            <div className="grid gap-2">
                                <HeadingSmall title="Assignee" description="Opsional, multi-select dari member project" />
                                <div className="grid max-h-48 grid-cols-2 gap-2 overflow-y-auto rounded-md border p-3">
                                    {members.map((user) => (
                                        <label key={user.id} className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={data.assignees.includes(user.id)}
                                                onCheckedChange={(checked) => toggleAssignee(user.id, checked === true)}
                                            />
                                            {user.name}
                                        </label>
                                    ))}
                                    {members.length === 0 && <span className="text-muted-foreground">Project ini belum punya member.</span>}
                                </div>
                                <InputError message={errors.assignees} />
                            </div>

                            <Button disabled={processing}>Simpan Perubahan</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

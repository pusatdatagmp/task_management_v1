// ==========================================================
// MODUL       : tasks/create
// KLASIFIKASI : UI
// TUJUAN      : Form buat task baru (admin only, F-29). due_date PRE-FILL +7 hari
//               DI SINI (F-68) — bukan default di model/migration.
// DIPANGGIL   : TaskController::create()
// MEMANGGIL   : route('tasks.store')
// DATA MASUK  : project {id,name}, members[] (dropdown assignee)
// DATA KELUAR : POST form -> TaskController::store()
// RISIKO      : task_type SENGAJA cuma tentative|project (Hari-4 §D3) —
//               daily/weekly/monthly lahir dari task_templates + recurring engine v0.8.
//               Revisi 2026-08-06 item 5: checklist_items (F-123, "subtask" ringan)
//               ikut dikirim, pola input IDENTIK task-templates/create.tsx.
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
import { FormEventHandler, useState } from 'react';

interface UserOption {
    id: number;
    name: string;
}

interface TaskCreateProps {
    project: { id: number; name: string };
    members: UserOption[];
}

// SUMBER: Hari-4 §D2 — due_date WAJIB, default +7 hari di FORM (F-68). JANGAN
// pindahkan default ini ke Task::booted() — admin yang lupa isi harus ditolak
// DB (NOT NULL), bukan diam-diam diisi.
function defaultDueDate(): string {
    const date = new Date();
    date.setDate(date.getDate() + 7);
    return date.toISOString().slice(0, 16);
}

export default function TaskCreate({ project, members }: TaskCreateProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Project', href: '/projects' },
        { title: project.name, href: route('projects.edit', project.id) },
        { title: 'Task', href: route('tasks.index', project.id) },
        { title: 'Task Baru', href: route('tasks.create', project.id) },
    ];

    const { data, setData, post, processing, errors } = useForm({
        title: '',
        description: '',
        task_type: 'tentative',
        // F-126: enum `priority` lama TIDAK dikirim dari form ini lagi (disembunyikan
        // dari UI) — kolom DB dipertahankan (default 'normal' di migration), tidak
        // dihapus. priority_quadrant nullable (F-122) — '' = belum diklasifikasi.
        priority_quadrant: '' as PriorityQuadrant | '',
        estimated_minutes: 60,
        points: 0,
        due_date: defaultDueDate(),
        assignees: [] as number[],
        // Revisi 2026-08-06 item 5: checklist ("subtask" ringan, F-123) diisi
        // LANGSUNG saat buat task — pola IDENTIK task-templates/create.tsx.
        checklist_items: [] as string[],
    });

    const [newChecklistText, setNewChecklistText] = useState('');

    const addChecklistItem = () => {
        if (!newChecklistText.trim()) return;
        setData('checklist_items', [...data.checklist_items, newChecklistText.trim()]);
        setNewChecklistText('');
    };

    const removeChecklistItem = (index: number) => {
        setData(
            'checklist_items',
            data.checklist_items.filter((_, i) => i !== index),
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('tasks.store', project.id));
    };

    const toggleAssignee = (id: number, checked: boolean) => {
        setData('assignees', checked ? [...data.assignees, id] : data.assignees.filter((a) => a !== id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Task Baru — ${project.name}`} />

            <div className="p-4">
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Task Baru</CardTitle>
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

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="task_type">Tipe task</Label>
                                    <Select value={data.task_type} onValueChange={(value) => setData('task_type', value)}>
                                        <SelectTrigger id="task_type">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="tentative">Tentative</SelectItem>
                                            <SelectItem value="project">Project</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.task_type} />
                                </div>

                                <div className="grid gap-2">
                                    {/* F-122/F-126: Eisenhower quadrant GANTIKAN enum priority lama di UI —
                                        enum lama tetap ada di DB (legacy, tersembunyi), tidak dihapus. */}
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

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                                <HeadingSmall title="Assignee" description="Opsional, multi-select dari member project" />
                                <div className="grid max-h-48 grid-cols-1 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
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

                            <div className="grid gap-2">
                                <HeadingSmall
                                    title="Checklist / Subtask"
                                    description="Syarat kerja ringan (F-123) — gate transisi ->review menolak submit kalau ada item belum dicentang."
                                />
                                <div className="flex flex-col gap-2">
                                    {data.checklist_items.map((text, index) => (
                                        <div key={index} className="flex items-center gap-2">
                                            <span className="flex-1 rounded-md border px-3 py-1.5 text-sm">{text}</span>
                                            <Button type="button" variant="outline" size="sm" onClick={() => removeChecklistItem(index)}>
                                                Hapus
                                            </Button>
                                        </div>
                                    ))}
                                    <div className="flex items-center gap-2">
                                        <Input
                                            placeholder="Tambah item checklist/subtask..."
                                            value={newChecklistText}
                                            onChange={(e) => setNewChecklistText(e.target.value)}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') {
                                                    e.preventDefault();
                                                    addChecklistItem();
                                                }
                                            }}
                                        />
                                        <Button type="button" size="sm" onClick={addChecklistItem} className="shrink-0">
                                            Tambah
                                        </Button>
                                    </div>
                                </div>
                                <InputError message={errors.checklist_items} />
                            </div>

                            <Button disabled={processing}>Simpan</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

// ==========================================================
// MODUL       : task-proposals/create
// KLASIFIKASI : UI
// TUJUAN      : F-186 — form member mengajukan task baru untuk dirinya sendiri.
//               Field SEPADAN tasks/create.tsx (form admin) TAPI tanpa
//               assignee/checklist (scope-out sengaja) — assignee dikunci ke
//               diri sendiri di server (TaskProposalController::store()). Tag
//               DITAMBAH (permintaan Boss 2026-09-04).
// DIPANGGIL   : TaskProposalController::create()
// MEMANGGIL   : route('task-proposals.store')
// DATA MASUK  : projects[] (project yang diikuti user login saja), availableTags[]
//               (katalog tag organisasi)
// DATA KELUAR : POST form -> TaskProposalController::store()
// RISIKO      : due_date PRE-FILL +7 hari DI SINI (F-68, pola sama tasks/create.tsx)
//               — bukan default di model/migration.
// ==========================================================

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text-editor';
import TagPicker, { type TagOption } from '@/components/tag-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { PRIORITY_QUADRANT_OPTIONS, type PriorityQuadrant } from '@/lib/priority-quadrant';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface ProjectOption {
    id: number;
    name: string;
}

interface TaskProposalCreateProps {
    projects: ProjectOption[];
    availableTags: TagOption[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Ajukan Tugas', href: '/task-proposals/create' }];

// SUMBER: sama seperti tasks/create.tsx — due_date WAJIB, default +7 hari di
// FORM (F-68), JANGAN dipindah ke model.
function defaultDueDate(): string {
    const date = new Date();
    date.setDate(date.getDate() + 7);
    return date.toISOString().slice(0, 16);
}

export default function TaskProposalCreate({ projects, availableTags }: TaskProposalCreateProps) {
    const { data, setData, post, processing, errors } = useForm({
        project_id: '',
        title: '',
        description: '',
        task_type: 'tentative',
        priority_quadrant: '' as PriorityQuadrant | '',
        estimated_minutes: 60,
        points: 0,
        due_date: defaultDueDate(),
        tags: [] as number[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('task-proposals.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ajukan Tugas" />

            <div className="p-4">
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Ajukan Tugas Baru</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {projects.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Kamu belum jadi member project mana pun — hubungi admin.</p>
                        ) : (
                            <form onSubmit={submit} className="space-y-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="project_id">Project</Label>
                                    <Select value={data.project_id} onValueChange={(value) => setData('project_id', value)}>
                                        <SelectTrigger id="project_id">
                                            <SelectValue placeholder="Pilih project" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {projects.map((p) => (
                                                <SelectItem key={p.id} value={String(p.id)}>
                                                    {p.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.project_id} />
                                </div>

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
                                        <Label htmlFor="priority_quadrant">Prioritas</Label>
                                        <Select
                                            value={data.priority_quadrant || '__none'}
                                            onValueChange={(value) =>
                                                setData('priority_quadrant', value === '__none' ? '' : (value as PriorityQuadrant))
                                            }
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
                                    <HeadingSmall
                                        title="Tag"
                                        description="Opsional"
                                    />
                                    <TagPicker tags={availableTags} selected={data.tags} onChange={(ids) => setData('tags', ids)} />
                                    <InputError message={errors.tags} />
                                </div>

                                <HeadingSmall
                                    title="Catatan"
                                    description="Pengajuan ini akan direview admin. Angka estimasi/poin/tenggat bisa dikoreksi admin sebelum disetujui."
                                />

                                <Button disabled={processing}>Ajukan</Button>
                            </form>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

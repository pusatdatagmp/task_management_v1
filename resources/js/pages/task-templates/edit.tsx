// ==========================================================
// MODUL       : task-templates/edit
// KLASIFIKASI : UI
// TUJUAN      : Form edit template recurring (admin only, F-46, task.manage).
//               Field sama dengan create, pre-filled dari data existing. Simpan
//               TIDAK PERNAH menyentuh instance tasks yang sudah lahir (A6).
// DIPANGGIL   : TaskTemplateController::edit()
// MEMANGGIL   : route('task-templates.update')
// DATA MASUK  : project, template (existing), members[]
// DATA KELUAR : PUT form -> TaskTemplateController::update()
// RISIKO      : sama seperti create.tsx — recurrence_config dikirim sesuai
//               task_type terpilih SAAT SUBMIT (A4), bukan bentuk lama template.
//               checklist_items (F-123) SELALU kirim SELURUH daftar — server
//               hapus-lalu-buat-ulang (TaskTemplateController::syncChecklistItems()).
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
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface UserOption {
    id: number;
    name: string;
}

interface TemplateData {
    id: number;
    title: string;
    description: string | null;
    task_type: 'daily' | 'weekly' | 'monthly';
    priority: string;
    estimated_minutes: number;
    points: number;
    recurrence_config: { day_of_week?: number; day_of_month?: number };
    default_assignees: number[];
    is_active: boolean;
    checklist_items: string[];
}

interface TaskTemplateEditProps {
    project: { id: number; name: string };
    template: TemplateData;
    members: UserOption[];
}

const DAY_OPTIONS = [
    { value: '1', label: 'Senin' },
    { value: '2', label: 'Selasa' },
    { value: '3', label: 'Rabu' },
    { value: '4', label: 'Kamis' },
    { value: '5', label: "Jum'at" },
    { value: '6', label: 'Sabtu' },
    { value: '7', label: 'Minggu' },
];

export default function TaskTemplateEdit({ project, template, members }: TaskTemplateEditProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Project', href: '/projects' },
        { title: project.name, href: route('projects.edit', project.id) },
        { title: 'Template Recurring', href: route('task-templates.index', project.id) },
        { title: `Edit ${template.title}`, href: '#' },
    ];

    const { data, setData, put, transform, processing, errors } = useForm({
        title: template.title,
        description: template.description ?? '',
        task_type: template.task_type,
        priority: template.priority,
        estimated_minutes: template.estimated_minutes,
        points: template.points,
        day_of_week: template.recurrence_config.day_of_week ?? 1,
        day_of_month: template.recurrence_config.day_of_month ?? 1,
        is_active: template.is_active,
        default_assignees: template.default_assignees,
        checklist_items: template.checklist_items,
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

    // SUMBER A4: sama seperti create.tsx — bentuk recurrence_config mengikuti
    // task_type yang TERPILIH SAAT SUBMIT, bukan bentuk lama yang tersimpan.
    transform((formData) => ({
        ...formData,
        recurrence_config:
            formData.task_type === 'weekly'
                ? { day_of_week: formData.day_of_week }
                : formData.task_type === 'monthly'
                  ? { day_of_month: formData.day_of_month }
                  : {},
    }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('task-templates.update', [project.id, template.id]));
    };

    const toggleAssignee = (id: number, checked: boolean) => {
        setData('default_assignees', checked ? [...data.default_assignees, id] : data.default_assignees.filter((a) => a !== id));
    };

    const errorBag = errors as Record<string, string>;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${template.title} — ${project.name}`} />

            <div className="p-4">
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Edit Template Recurring</CardTitle>
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
                                    <Label htmlFor="task_type">Tipe pengulangan</Label>
                                    <Select
                                        value={data.task_type}
                                        onValueChange={(value) => setData('task_type', value as 'daily' | 'weekly' | 'monthly')}
                                    >
                                        <SelectTrigger id="task_type">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="daily">Harian</SelectItem>
                                            <SelectItem value="weekly">Mingguan</SelectItem>
                                            <SelectItem value="monthly">Bulanan</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.task_type} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="priority">Prioritas</Label>
                                    <Select value={data.priority} onValueChange={(value) => setData('priority', value)}>
                                        <SelectTrigger id="priority">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="low">Low</SelectItem>
                                            <SelectItem value="normal">Normal</SelectItem>
                                            <SelectItem value="high">High</SelectItem>
                                            <SelectItem value="urgent">Urgent</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.priority} />
                                </div>
                            </div>

                            {data.task_type === 'weekly' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="day_of_week">Hari (tiap minggu)</Label>
                                    <Select value={String(data.day_of_week)} onValueChange={(value) => setData('day_of_week', Number(value))}>
                                        <SelectTrigger id="day_of_week">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {DAY_OPTIONS.map((day) => (
                                                <SelectItem key={day.value} value={day.value}>
                                                    {day.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errorBag['recurrence_config.day_of_week']} />
                                </div>
                            )}

                            {data.task_type === 'monthly' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="day_of_month">Tanggal (tiap bulan)</Label>
                                    <Input
                                        id="day_of_month"
                                        type="number"
                                        min={1}
                                        max={31}
                                        value={data.day_of_month}
                                        onChange={(e) => setData('day_of_month', Number(e.target.value))}
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Tanggal lebih besar dari jumlah hari bulan otomatis dipakaikan ke hari terakhir bulan itu (F-101).
                                    </p>
                                    <InputError message={errorBag['recurrence_config.day_of_month']} />
                                </div>
                            )}

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

                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                                Aktif (generate sesuai jadwal)
                            </label>

                            <div className="grid gap-2">
                                <HeadingSmall
                                    title="Default assignee"
                                    description="Opsional, multi-select dari member project. Divalidasi ulang saat generate (F-86)."
                                />
                                <div className="grid max-h-48 grid-cols-2 gap-2 overflow-y-auto rounded-md border p-3">
                                    {members.map((user) => (
                                        <label key={user.id} className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={data.default_assignees.includes(user.id)}
                                                onCheckedChange={(checked) => toggleAssignee(user.id, checked === true)}
                                            />
                                            {user.name}
                                        </label>
                                    ))}
                                    {members.length === 0 && <span className="text-muted-foreground">Project ini belum punya member.</span>}
                                </div>
                                <InputError message={errors.default_assignees} />
                            </div>

                            <div className="grid gap-2">
                                <HeadingSmall
                                    title="Checklist"
                                    description="Disalin ke tiap instance task saat generate (F-123). Perubahan di sini TIDAK menyentuh instance yang sudah lahir (A6)."
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
                                            placeholder="Tambah item checklist..."
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

                            <Button disabled={processing}>Simpan Perubahan</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

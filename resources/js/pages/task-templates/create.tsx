// ==========================================================
// MODUL       : task-templates/create
// KLASIFIKASI : UI
// TUJUAN      : Form buat template recurring baru (admin only, F-46, task.manage).
//               task_type HANYA daily/weekly/monthly (A2) — tentative/project tidak
//               berulang, tidak muncul di sini (beda dari tasks/create.tsx).
// DIPANGGIL   : TaskTemplateController::create()
// MEMANGGIL   : route('task-templates.store')
// DATA MASUK  : project {id,name}, members[] (checkbox default_assignees)
// DATA KELUAR : POST form -> TaskTemplateController::store()
// RISIKO      : recurrence_config HANYA dikirim sesuai task_type terpilih (A4) —
//               field day_of_week/day_of_month yang tidak relevan disembunyikan,
//               bukan dikirim kosong lalu diabaikan diam-diam di server.
//               checklist_items (F-123) SELALU kirim SELURUH daftar (bukan diff) —
//               server hapus-lalu-buat-ulang tiap simpan (TaskTemplateController).
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

interface TaskTemplateCreateProps {
    project: { id: number; name: string };
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

export default function TaskTemplateCreate({ project, members }: TaskTemplateCreateProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Project', href: '/projects' },
        { title: project.name, href: route('projects.edit', project.id) },
        { title: 'Template Recurring', href: route('task-templates.index', project.id) },
        { title: 'Template Baru', href: route('task-templates.create', project.id) },
    ];

    const { data, setData, post, transform, processing, errors } = useForm({
        title: '',
        description: '',
        task_type: 'daily' as 'daily' | 'weekly' | 'monthly',
        priority: 'normal',
        estimated_minutes: 60,
        points: 0,
        day_of_week: 1,
        day_of_month: 1,
        is_active: true as boolean,
        default_assignees: [] as number[],
        // F-123/F-127: disalin ke task_checklist_items instance TIAP kali template
        // ini melahirkan task (GenerateRecurringTasksCommand). Urutan array = position.
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

    // SUMBER A4: bentuk recurrence_config beda per task_type — daily dikirim [],
    // weekly {day_of_week}, monthly {day_of_month}. day_of_week/day_of_month lokal
    // (state form) TETAP ikut terkirim juga, tapi TaskTemplateController hanya
    // membaca field yang lolos validasi (recurrence_config), jadi aman diabaikan.
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
        post(route('task-templates.store', project.id));
    };

    const toggleAssignee = (id: number, checked: boolean) => {
        setData('default_assignees', checked ? [...data.default_assignees, id] : data.default_assignees.filter((a) => a !== id));
    };

    // SUMBER: error nested 'recurrence_config.day_of_week'/'recurrence_config.day_of_month'
    // (StoreTaskTemplateRequest) bukan key literal di objek `data` form ini (day_of_week/
    // day_of_month lokal, digabung jadi recurrence_config saat transform) — cast longgar
    // di sini murni supaya TypeScript tidak menolak akses key dot-notation dari server.
    const errorBag = errors as Record<string, string>;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Template Baru — ${project.name}`} />

            <div className="p-4">
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Template Recurring Baru</CardTitle>
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
                                Aktif (langsung mulai generate sesuai jadwal)
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
                                    description="Disalin ke tiap instance task saat generate (F-123). Gate transisi ->review menolak submit kalau ada item belum dicentang."
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

                            <Button disabled={processing}>Simpan</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

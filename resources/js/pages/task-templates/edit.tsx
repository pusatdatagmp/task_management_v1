// ==========================================================
// MODUL       : task-templates/edit
// KLASIFIKASI : UI
// TUJUAN      : Form edit template recurring (admin only, F-46, task.manage).
//               Field sama dengan create, pre-filled dari data existing. Simpan
//               TIDAK PERNAH menyentuh instance tasks yang sudah lahir (A6).
//               AE-2b (F-158): seksi "Konfigurasi Automation Engine" sama seperti
//               create.tsx, pre-filled dari anchor_config/date_window_config
//               tersimpan -- lihat create.tsx untuk penjelasan lengkap tiap field.
// DIPANGGIL   : TaskTemplateController::edit()
// MEMANGGIL   : route('task-templates.update')
// DATA MASUK  : project, template (existing), members[]
// DATA KELUAR : PUT form -> TaskTemplateController::update()
// RISIKO      : checklist_items (F-123) SELALU kirim SELURUH daftar — server
//               hapus-lalu-buat-ulang (TaskTemplateController::syncChecklistItems()).
//               anchor_day_type (F-74) DITURUNKAN dari anchor_config existing --
//               ada day_of_month -> 'month', selain itu default 'week' (termasuk
//               anchor_config kosong, mis. template belum pernah dikonfigurasi C).
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

interface TemplateData {
    id: number;
    title: string;
    description: string | null;
    priority: string;
    priority_quadrant: PriorityQuadrant | null;
    estimated_minutes: number;
    points: number;
    // Revisi 2026-08-06 item 7.
    due_offset_days: number | null;
    default_assignees: number[];
    is_active: boolean;
    checklist_items: string[];
    // AE-2b (F-158): kolom Automation Engine, sudah ada sejak AE-1.
    anchor_strategy: 'time_based' | 'completion_based' | 'calendar_anchored';
    interval_value: number | null;
    interval_unit: 'day' | 'week' | 'month' | null;
    anchor_config: { day_of_week?: number; day_of_month?: number } | null;
    date_window_config: { weekdays?: number[]; dom_min?: number; dom_max?: number } | null;
    max_active_instances: number | null;
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

// C3 (opsional, prompt AE-2b) — lihat create.tsx untuk penjelasan lengkap,
// implementasi IDENTIK (ringkasan teks murni, bukan simulasi tanggal server).
function describeSchedule(
    anchorStrategy: string,
    intervalValue: number,
    intervalUnit: string,
    dayType: string,
    dayOfWeek: number,
    dayOfMonth: number,
): string {
    const dayLabel = (value: number) => DAY_OPTIONS.find((d) => d.value === String(value))?.label ?? '?';
    const unitLabel: Record<string, string> = { day: 'hari', week: 'minggu', month: 'bulan' };

    if (anchorStrategy === 'time_based') {
        return `Preview: generate tiap ${intervalValue} ${unitLabel[intervalUnit] ?? intervalUnit}, dihitung dari terakhir kali generate (otomatis geser kalau jatuh di libur/akhir pekan).`;
    }
    if (anchorStrategy === 'completion_based') {
        return `Preview: generate tiap ${intervalValue} ${unitLabel[intervalUnit] ?? intervalUnit} SETELAH instance periode sebelumnya SELESAI — kalau belum, ditunda dan admin dinotifikasi sekali.`;
    }
    if (dayType === 'week') {
        return `Preview: generate tiap hari ${dayLabel(dayOfWeek)} (geser ke hari kerja berikutnya kalau libur/akhir pekan).`;
    }

    return `Preview: generate tiap tanggal ${dayOfMonth} (digeser ke hari TERAKHIR bulan itu kalau bulan lebih pendek, lalu ke hari kerja berikutnya kalau libur).`;
}

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
        // F-175: enum `priority` lama TIDAK dikirim dari form ini lagi (disembunyikan
        // dari UI, pola identik create.tsx/tasks-edit.tsx F-126) — kolom DB
        // dipertahankan, tidak dihapus. priority_quadrant nullable — '' = belum
        // diklasifikasi.
        priority_quadrant: (template.priority_quadrant ?? '') as PriorityQuadrant | '',
        estimated_minutes: template.estimated_minutes,
        points: template.points,
        // Revisi 2026-08-06 item 7: null -> '' di form (input number kosong).
        due_offset_days: (template.due_offset_days ?? '') as number | '',
        is_active: template.is_active,
        default_assignees: template.default_assignees,
        checklist_items: template.checklist_items,

        // AE-2b (F-158): pre-filled dari kolom Automation Engine existing.
        anchor_strategy: template.anchor_strategy,
        interval_value: template.interval_value ?? 1,
        interval_unit: template.interval_unit ?? 'day',
        // F-74: diturunkan dari anchor_config -- ada day_of_month -> 'month',
        // selain itu default 'week' (termasuk anchor_config kosong/null).
        anchor_day_type: (template.anchor_config?.day_of_month !== undefined ? 'month' : 'week') as 'week' | 'month',
        anchor_day_of_week: template.anchor_config?.day_of_week ?? 1,
        anchor_day_of_month: template.anchor_config?.day_of_month ?? 1,
        date_window_weekdays: template.date_window_config?.weekdays ?? ([] as number[]),
        date_window_dom_min: (template.date_window_config?.dom_min ?? '') as number | '',
        date_window_dom_max: (template.date_window_config?.dom_max ?? '') as number | '',
        max_active_instances: (template.max_active_instances ?? '') as number | '',
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

    // Revisi 2026-08-07 (permintaan Boss): dropdown task_type & recurrence_config
    // lama DICABUT, lihat create.tsx untuk penjelasan lengkap.
    // AE-2b: anchor_config/date_window_config -- lihat create.tsx transform()
    // untuk penjelasan lengkap, logika IDENTIK.
    // BUG FIX (ditemukan Boss 2026-08-06) -- lihat create.tsx transform() untuk
    // penjelasan lengkap: anchor_day_type WAJIB di-null-kan kalau strategi bukan
    // calendar_anchored, atau required_if:anchor_day_type,week di server salah
    // memaksa anchor_config.day_of_week terisi untuk SEMUA strategi.
    transform((formData) => ({
        ...formData,
        anchor_day_type: formData.anchor_strategy === 'calendar_anchored' ? formData.anchor_day_type : null,
        anchor_config:
            formData.anchor_strategy === 'calendar_anchored'
                ? formData.anchor_day_type === 'week'
                    ? { day_of_week: formData.anchor_day_of_week }
                    : { day_of_month: formData.anchor_day_of_month }
                : {},
        date_window_config: {
            weekdays: formData.date_window_weekdays,
            dom_min: formData.date_window_dom_min === '' ? undefined : formData.date_window_dom_min,
            dom_max: formData.date_window_dom_max === '' ? undefined : formData.date_window_dom_max,
        },
        max_active_instances: formData.max_active_instances === '' ? undefined : formData.max_active_instances,
        due_offset_days: formData.due_offset_days === '' ? undefined : formData.due_offset_days, // revisi 2026-08-06 item 7
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

                            <div className="grid gap-2">
                                {/* F-175: Eisenhower quadrant GANTIKAN enum priority lama di UI —
                                    pola identik create.tsx (F-122/F-126). Enum lama tetap ada di
                                    DB (legacy, tersembunyi), tidak dihapus. */}
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

                            <div className="grid gap-4 rounded-md border p-4">
                                <HeadingSmall
                                    title="Konfigurasi Automation Engine"
                                    description="Atur KAPAN & SEBERAPA SERING template ini melahirkan task -- interval bebas (mis. tiap 3 hari, tiap 2 minggu) atau hari tetap."
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="anchor_strategy">Strategi jadwal</Label>
                                    <Select
                                        value={data.anchor_strategy}
                                        onValueChange={(value) => setData('anchor_strategy', value as typeof data.anchor_strategy)}
                                    >
                                        <SelectTrigger id="anchor_strategy">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="time_based">A — Interval tetap (tiap N hari/minggu/bulan)</SelectItem>
                                            <SelectItem value="completion_based">B — Tunggu selesai (interval + periode sebelumnya SELESAI)</SelectItem>
                                            <SelectItem value="calendar_anchored">C — Hari tetap (mis. tiap Senin, atau tanggal tertentu)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.anchor_strategy} />
                                </div>

                                {(data.anchor_strategy === 'time_based' || data.anchor_strategy === 'completion_based') && (
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="interval_value">Tiap</Label>
                                            <Input
                                                id="interval_value"
                                                type="number"
                                                min={1}
                                                value={data.interval_value}
                                                onChange={(e) => setData('interval_value', Number(e.target.value))}
                                                required
                                            />
                                            <InputError message={errors.interval_value} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="interval_unit">Satuan</Label>
                                            <Select
                                                value={data.interval_unit}
                                                onValueChange={(value) => setData('interval_unit', value as typeof data.interval_unit)}
                                            >
                                                <SelectTrigger id="interval_unit">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="day">Hari</SelectItem>
                                                    <SelectItem value="week">Minggu</SelectItem>
                                                    <SelectItem value="month">Bulan</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.interval_unit} />
                                        </div>
                                    </div>
                                )}

                                {data.anchor_strategy === 'completion_based' && (
                                    <p className="text-xs text-muted-foreground">
                                        Instance BARU hanya lahir kalau instance periode sebelumnya sudah SELESAI (mencegah backlog menumpuk).
                                        Admin dinotifikasi SEKALI kalau macet menunggu (F-154).
                                    </p>
                                )}

                                {data.anchor_strategy === 'calendar_anchored' && (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="anchor_day_type">Jenis hari tetap</Label>
                                            <Select
                                                value={data.anchor_day_type}
                                                onValueChange={(value) => setData('anchor_day_type', value as typeof data.anchor_day_type)}
                                            >
                                                <SelectTrigger id="anchor_day_type">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="week">Hari dalam minggu (mis. tiap Senin)</SelectItem>
                                                    <SelectItem value="month">Tanggal dalam bulan (mis. tgl 1)</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.anchor_day_type} />
                                        </div>

                                        {data.anchor_day_type === 'week' && (
                                            <div className="grid gap-2">
                                                <Label htmlFor="anchor_day_of_week">Hari</Label>
                                                <Select
                                                    value={String(data.anchor_day_of_week)}
                                                    onValueChange={(value) => setData('anchor_day_of_week', Number(value))}
                                                >
                                                    <SelectTrigger id="anchor_day_of_week">
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
                                                <InputError message={errorBag['anchor_config.day_of_week']} />
                                            </div>
                                        )}

                                        {data.anchor_day_type === 'month' && (
                                            <div className="grid gap-2">
                                                <Label htmlFor="anchor_day_of_month">Tanggal</Label>
                                                <Input
                                                    id="anchor_day_of_month"
                                                    type="number"
                                                    min={1}
                                                    max={31}
                                                    value={data.anchor_day_of_month}
                                                    onChange={(e) => setData('anchor_day_of_month', Number(e.target.value))}
                                                    required
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    Tanggal lebih besar dari jumlah hari bulan otomatis digeser ke hari TERAKHIR bulan itu
                                                    (F-164, mis. tgl 31 di Februari → generate tgl 28/29).
                                                </p>
                                                <InputError message={errorBag['anchor_config.day_of_month']} />
                                            </div>
                                        )}
                                    </>
                                )}

                                <p className="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
                                    {describeSchedule(
                                        data.anchor_strategy,
                                        data.interval_value,
                                        data.interval_unit,
                                        data.anchor_day_type,
                                        data.anchor_day_of_week,
                                        data.anchor_day_of_month,
                                    )}
                                </p>

                                <div className="grid gap-2">
                                    <Label>Batasi hari boleh generate (opsional)</Label>
                                    <div className="flex flex-wrap gap-3">
                                        {DAY_OPTIONS.map((day) => (
                                            <label key={day.value} className="flex items-center gap-1.5 text-sm">
                                                <Checkbox
                                                    checked={data.date_window_weekdays.includes(Number(day.value))}
                                                    onCheckedChange={(checked) =>
                                                        setData(
                                                            'date_window_weekdays',
                                                            checked === true
                                                                ? [...data.date_window_weekdays, Number(day.value)]
                                                                : data.date_window_weekdays.filter((d) => d !== Number(day.value)),
                                                        )
                                                    }
                                                />
                                                {day.label}
                                            </label>
                                        ))}
                                    </div>
                                    <p className="text-xs text-muted-foreground">Kosong = tak ada batasan hari.</p>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="date_window_dom_min">Tanggal minimum (opsional)</Label>
                                        <Input
                                            id="date_window_dom_min"
                                            type="number"
                                            min={1}
                                            max={31}
                                            value={data.date_window_dom_min}
                                            onChange={(e) => setData('date_window_dom_min', e.target.value === '' ? '' : Number(e.target.value))}
                                        />
                                        <InputError message={errorBag['date_window_config.dom_min']} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="date_window_dom_max">Tanggal maksimum (opsional)</Label>
                                        <Input
                                            id="date_window_dom_max"
                                            type="number"
                                            min={1}
                                            max={31}
                                            value={data.date_window_dom_max}
                                            onChange={(e) => setData('date_window_dom_max', e.target.value === '' ? '' : Number(e.target.value))}
                                        />
                                        <InputError message={errorBag['date_window_config.dom_max']} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="max_active_instances">Kuota maks instance belum-selesai (opsional)</Label>
                                    <Input
                                        id="max_active_instances"
                                        type="number"
                                        min={1}
                                        value={data.max_active_instances}
                                        onChange={(e) => setData('max_active_instances', e.target.value === '' ? '' : Number(e.target.value))}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Kosong = tak terbatas. Kalau instance belum-selesai sudah mencapai batas ini, generate berikutnya
                                        di-skip sampai ada yang selesai.
                                    </p>
                                    <InputError message={errors.max_active_instances} />
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
                                <Label htmlFor="due_offset_days">Tenggat (hari kerja setelah muncul)</Label>
                                <Input
                                    id="due_offset_days"
                                    type="number"
                                    min={1}
                                    max={365}
                                    placeholder="Kosong = jatuh tempo hari yang sama (perilaku lama)"
                                    value={data.due_offset_days}
                                    onChange={(e) => setData('due_offset_days', e.target.value === '' ? '' : Number(e.target.value))}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Berapa hari KERJA (lewati akhir pekan/libur) setelah task ini muncul, sampai jatuh tempo. Kosong = task
                                    langsung jatuh tempo di hari yang sama saat lahir.
                                </p>
                                <InputError message={errors.due_offset_days} />
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
                                <div className="grid max-h-48 grid-cols-1 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
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

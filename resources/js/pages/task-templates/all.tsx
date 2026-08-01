// ==========================================================
// MODUL       : task-templates/all
// KLASIFIKASI : UI
// TUJUAN      : Halaman "Tugas Berulang" (F-140/F-144/F-147, v1.2 H7b) — listing
//               template recurring lintas SEMUA project (nav sebelumnya disabled).
//               MURNI navigasi/listing baru — CRUD (create/edit/toggle-active) TETAP
//               lewat route project-scoped yang sudah ada (F-46 utuh, nol endpoint baru).
// DIPANGGIL   : TaskTemplateController::allProjects()
// MEMANGGIL   : route('task-templates.create'/'edit'/'toggle-active', projectId, ...)
// DATA MASUK  : templates[] (dengan relasi project), projects[] (untuk pilih target Buat Baru)
// DATA KELUAR : navigasi create/edit, PATCH toggle-active (endpoint lama)
// RISIKO      : "Template Baru" WAJIB project dipilih dulu (F-46 — template selalu
//               milik 1 project, tidak ada versi lintas-project) — tombol disabled
//               sampai dropdown terisi, supaya tidak navigasi ke URL project undefined.
// ==========================================================

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface ProjectOption {
    id: number;
    name: string;
}

interface TemplateRow {
    id: number;
    title: string;
    task_type: 'daily' | 'weekly' | 'monthly';
    estimated_minutes: number;
    points: number;
    priority: 'low' | 'normal' | 'high' | 'urgent';
    recurrence_config: { day_of_week?: number; day_of_month?: number };
    is_active: boolean;
    project: ProjectOption;
}

const DAY_NAMES = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', "Jum'at", 'Sabtu', 'Minggu'];

function jadwalLabel(template: TemplateRow): string {
    if (template.task_type === 'daily') return 'Tiap hari kerja';
    if (template.task_type === 'weekly') return `Tiap ${DAY_NAMES[template.recurrence_config.day_of_week ?? 0]}`;

    return `Tanggal ${template.recurrence_config.day_of_month ?? '-'} tiap bulan`;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tugas Berulang', href: '/task-templates' }];

export default function AllTaskTemplates({ templates, projects }: { templates: TemplateRow[]; projects: ProjectOption[] }) {
    const [targetProject, setTargetProject] = useState<string>('');

    const toggleActive = (template: TemplateRow) => {
        router.patch(route('task-templates.toggle-active', [template.project.id, template.id]), {}, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tugas Berulang" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Tugas Berulang</h1>
                    <div className="flex items-center gap-2">
                        <select
                            className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            value={targetProject}
                            onChange={(e) => setTargetProject(e.target.value)}
                        >
                            <option value="">Pilih project...</option>
                            {projects.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                        <Button disabled={!targetProject} asChild={!!targetProject}>
                            {targetProject ? <Link href={route('task-templates.create', targetProject)}>Template Baru</Link> : <span>Template Baru</span>}
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-muted-foreground">
                                <th className="p-3">Project</th>
                                <th className="p-3">Judul</th>
                                <th className="p-3">Jadwal</th>
                                <th className="p-3">Estimasi</th>
                                <th className="p-3">Poin</th>
                                <th className="p-3">Prioritas</th>
                                <th className="p-3">Status</th>
                                <th className="p-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {templates.map((template) => (
                                <tr key={template.id} className="border-b last:border-0">
                                    <td className="p-3">{template.project.name}</td>
                                    <td className="p-3 font-medium">{template.title}</td>
                                    <td className="p-3">{jadwalLabel(template)}</td>
                                    <td className="p-3">{template.estimated_minutes}m</td>
                                    <td className="p-3">{template.points}</td>
                                    <td className="p-3 capitalize">{template.priority}</td>
                                    <td className="p-3">
                                        {template.is_active ? <Badge>Aktif</Badge> : <Badge variant="secondary">Nonaktif</Badge>}
                                    </td>
                                    <td className="p-3">
                                        <div className="flex gap-2">
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={route('task-templates.edit', [template.project.id, template.id])}>Edit</Link>
                                            </Button>
                                            <Button variant="outline" size="sm" onClick={() => toggleActive(template)}>
                                                {template.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {templates.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="p-6 text-center text-muted-foreground">
                                        Belum ada template recurring di project manapun.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

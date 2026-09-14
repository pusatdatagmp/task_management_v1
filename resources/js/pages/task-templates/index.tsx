// ==========================================================
// MODUL       : task-templates/index
// KLASIFIKASI : UI
// TUJUAN      : Daftar template recurring per project (F-46). is_active di-toggle
//               LANGSUNG dari sini (A5) — mati/hidup TIDAK menyentuh instance
//               tasks yang sudah lahir sebelumnya.
// DIPANGGIL   : TaskTemplateController::index()
// MEMANGGIL   : route('task-templates.create'/'edit'/'toggle-active')
// DATA MASUK  : project {id,name}, templates[] (urut judul)
// DATA KELUAR : navigasi create/edit, PATCH toggle-active
// RISIKO      : Revisi 2026-08-07 (permintaan Boss): kolom "Jadwal" dulu baca
//               task_type+recurrence_config lokal (statis, sering basi kalau
//               Boss pakai interval custom AE-2b) -- sekarang langsung pakai
//               `schedule_label` dari server (TaskTemplate::scheduleLabel()),
//               SATU sumber sama dengan yang dipakai GenerateTaskAction.
// ==========================================================

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { PRIORITY_QUADRANT_COLOR, PRIORITY_QUADRANT_LABEL, type PriorityQuadrant } from '@/lib/priority-quadrant';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface TemplateRow {
    id: number;
    title: string;
    schedule_label: string;
    estimated_minutes: number;
    points: number;
    // F-175: enum lama dipertahankan di DB (legacy), UI pakai priority_quadrant.
    priority_quadrant: PriorityQuadrant | null;
    is_active: boolean;
}

export default function TaskTemplatesIndex({ project, templates }: { project: { id: number; name: string }; templates: TemplateRow[] }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Project', href: '/projects' },
        { title: project.name, href: route('projects.edit', project.id) },
        { title: 'Template Recurring', href: route('task-templates.index', project.id) },
    ];

    const toggleActive = (template: TemplateRow) => {
        router.patch(route('task-templates.toggle-active', [project.id, template.id]), {}, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Template Recurring — ${project.name}`} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">Template Recurring: {project.name}</h1>
                    <Button asChild>
                        <Link href={route('task-templates.create', project.id)}>Template Baru</Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-muted-foreground">
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
                                    <td className="p-3 font-medium">{template.title}</td>
                                    <td className="p-3">{template.schedule_label}</td>
                                    <td className="p-3">{template.estimated_minutes}m</td>
                                    <td className="p-3">{template.points}</td>
                                    <td className="p-3">
                                        {template.priority_quadrant ? (
                                            <Badge
                                                style={{
                                                    backgroundColor: PRIORITY_QUADRANT_COLOR[template.priority_quadrant],
                                                    color: '#fff',
                                                    borderColor: 'transparent',
                                                }}
                                            >
                                                {PRIORITY_QUADRANT_LABEL[template.priority_quadrant]}
                                            </Badge>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">Belum diklasifikasi</span>
                                        )}
                                    </td>
                                    <td className="p-3">
                                        {template.is_active ? <Badge>Aktif</Badge> : <Badge variant="secondary">Nonaktif</Badge>}
                                    </td>
                                    <td className="p-3">
                                        <div className="flex gap-2">
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={route('task-templates.edit', [project.id, template.id])}>Edit</Link>
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
                                    <td colSpan={7} className="p-6 text-center text-muted-foreground">
                                        Belum ada template recurring.
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

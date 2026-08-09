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
//               Revisi 2026-08-06: search (judul) + filter Project/Status SISI
//               BROWSER (jumlah template per organisasi kecil, sudah termuat
//               penuh -- nol round-trip server, konsisten pola tasks/my-tasks.tsx).
//               Dropdown filter Project TERPISAH dari targetProject (dipakai
//               tombol "Template Baru") -- beda tujuan, jangan digabung.
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
    schedule_label: string;
    estimated_minutes: number;
    points: number;
    priority: 'low' | 'normal' | 'high' | 'urgent';
    is_active: boolean;
    project: ProjectOption;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tugas Berulang', href: '/task-templates' }];

export default function AllTaskTemplates({ templates, projects }: { templates: TemplateRow[]; projects: ProjectOption[] }) {
    const [targetProject, setTargetProject] = useState<string>('');
    const [search, setSearch] = useState('');
    const [filterProjectId, setFilterProjectId] = useState<number | ''>('');
    const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'inactive'>('all');

    const toggleActive = (template: TemplateRow) => {
        router.patch(route('task-templates.toggle-active', [template.project.id, template.id]), {}, { preserveScroll: true });
    };

    const filteredTemplates = templates.filter((t) => {
        if (search.trim() && !t.title.toLowerCase().includes(search.trim().toLowerCase())) return false;
        if (filterProjectId && t.project.id !== filterProjectId) return false;
        if (statusFilter === 'active' && !t.is_active) return false;
        if (statusFilter === 'inactive' && t.is_active) return false;
        return true;
    });
    const hasActiveFilter = search.trim() !== '' || filterProjectId !== '' || statusFilter !== 'all';

    const resetFilters = () => {
        setSearch('');
        setFilterProjectId('');
        setStatusFilter('all');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tugas Berulang" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
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

                <div className="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm">
                    <input
                        type="text"
                        placeholder="Cari judul..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="h-9 w-48 rounded-md border border-input bg-background px-2 text-sm"
                    />
                    <select
                        className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                        value={filterProjectId}
                        onChange={(e) => setFilterProjectId(e.target.value ? Number(e.target.value) : '')}
                    >
                        <option value="">Semua Project</option>
                        {projects.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </select>
                    <select
                        className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value as typeof statusFilter)}
                    >
                        <option value="all">Semua Status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                    {hasActiveFilter && (
                        <Button type="button" variant="ghost" size="sm" onClick={resetFilters}>
                            Reset filter
                        </Button>
                    )}
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
                            {filteredTemplates.map((template) => (
                                <tr key={template.id} className="border-b last:border-0">
                                    <td className="p-3">{template.project.name}</td>
                                    <td className="p-3 font-medium">{template.title}</td>
                                    <td className="p-3">{template.schedule_label}</td>
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

                            {templates.length > 0 && filteredTemplates.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="p-6 text-center text-muted-foreground">
                                        Tidak ada template yang cocok dengan filter ini.
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

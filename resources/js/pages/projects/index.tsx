// ==========================================================
// MODUL       : projects/index
// KLASIFIKASI : UI
// TUJUAN      : Daftar project AKTIF — admin lihat semua, member hanya yang
//               di-assign (filter sudah dilakukan server-side di
//               ProjectController::index()). Search + sort kolom SISI BROWSER
//               (jumlah project per organisasi kecil, sudah termuat penuh
//               sekaligus, nol round-trip server), pola SAMA task-templates/all.tsx
//               & widget "Status Project" di command-center.tsx.
//               Revisi 2026-08-07 (permintaan Boss): project DIARSIPKAN pindah
//               TOTAL ke halaman terpisah projects/archive.tsx -- backend
//               index() SEKARANG cuma kirim is_archived=false, jadi filter
//               status & kolom Status di halaman ini DICABUT (selalu "Aktif",
//               nol makna ditampilkan berulang).
// DIPANGGIL   : ProjectController::index()
// MEMANGGIL   : route('projects.create'/'projects.edit'/'projects.archive'/'projects.archived')
// DATA MASUK  : projects[] (sudah difilter sesuai role, HANYA is_archived=false)
// DATA KELUAR : navigasi ke create/edit/archive (halaman), PATCH archive (aksi)
// RISIKO      : F-90 — tombol digating PER PERMISSION (project.manage vs
//               status.manage), bukan satu boolean isAdmin. HANYA gating
//               tampilan — penegakan asli tetap di middleware `can:xxx` server.
// ==========================================================

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { confirmAction } from '@/lib/swal';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface ProjectRow {
    id: number;
    name: string;
    description: string | null;
    is_archived: boolean;
    owner: { id: number; name: string } | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Project', href: '/projects' }];

type SortKey = 'name' | 'owner';

export default function ProjectsIndex({ projects }: { projects: ProjectRow[] }) {
    const { auth } = usePage<SharedData>().props;
    const can = (permission: string) => auth.permissions.includes(permission);

    const [search, setSearch] = useState('');
    const [sort, setSort] = useState<{ key: SortKey; dir: 'asc' | 'desc' }>({ key: 'name', dir: 'asc' });

    const toggleSort = (key: SortKey) => {
        setSort((prev) => (prev.key === key ? { key, dir: prev.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: 'asc' }));
    };

    const filteredProjects = projects
        .filter((p) => {
            if (!search.trim()) return true;
            const q = search.trim().toLowerCase();

            return p.name.toLowerCase().includes(q) || (p.owner?.name.toLowerCase().includes(q) ?? false);
        })
        .sort((a, b) => {
            const { key, dir } = sort;
            const cmp = key === 'name' ? a.name.localeCompare(b.name) : (a.owner?.name ?? '').localeCompare(b.owner?.name ?? '');

            return dir === 'asc' ? cmp : -cmp;
        });

    const sortArrow = (key: SortKey) => (sort.key === key ? <span>{sort.dir === 'asc' ? '↑' : '↓'}</span> : null);

    const archive = async (project: ProjectRow) => {
        if (await confirmAction(`Arsipkan project "${project.name}"? Project yang diarsipkan tidak muncul lagi di daftar aktif.`, { danger: true })) {
            router.patch(route('projects.archive', project.id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Project" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">Project</h1>
                    <div className="flex items-center gap-2">
                        {can('project.manage') && (
                            <Button variant="outline" asChild>
                                <Link href={route('projects.archived')}>Lihat Arsip</Link>
                            </Button>
                        )}
                        {can('project.manage') && (
                            <Button asChild>
                                <Link href={route('projects.create')}>Project Baru</Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm">
                    <input
                        type="text"
                        placeholder="Cari nama/owner..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="h-9 w-48 rounded-md border border-input bg-background px-2 text-sm"
                    />
                    {search.trim() !== '' && (
                        <Button type="button" variant="ghost" size="sm" onClick={() => setSearch('')}>
                            Reset filter
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-muted-foreground">
                                {(
                                    [
                                        ['name', 'Nama'],
                                        ['owner', 'Owner'],
                                    ] as [SortKey, string][]
                                ).map(([key, label]) => (
                                    <th key={key} className="p-3">
                                        <button type="button" className="flex items-center gap-1 font-medium hover:text-foreground" onClick={() => toggleSort(key)}>
                                            {label}
                                            {sortArrow(key)}
                                        </button>
                                    </th>
                                ))}
                                <th className="p-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredProjects.map((project) => (
                                <tr key={project.id} className="border-b last:border-0">
                                    <td className="p-3">
                                        <div className="font-medium">{project.name}</div>
                                        {project.description && <div className="text-muted-foreground">{project.description}</div>}
                                    </td>
                                    <td className="p-3">{project.owner?.name ?? '-'}</td>
                                    <td className="p-3">
                                        <div className="flex gap-2">
                                            {/* 2026-08-08 (permintaan Boss): label "Detail" -- halaman tasks/index
                                                SEKARANG juga tampilkan deskripsi + anggota project, jadi berfungsi
                                                ganda sebagai detail. Nol halaman baru (reuse F-109-style). */}
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={route('tasks.index', project.id)}>Detail</Link>
                                            </Button>
                                            {can('project.manage') && (
                                                <>
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={route('projects.edit', project.id)}>Edit</Link>
                                                    </Button>
                                                    <Button variant="outline" size="sm" onClick={() => archive(project)}>
                                                        Arsipkan
                                                    </Button>
                                                </>
                                            )}
                                            {can('status.manage') && (
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={route('task-statuses.index', project.id)}>Status</Link>
                                                </Button>
                                            )}
                                            {can('task.manage') && (
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={route('task-templates.index', project.id)}>Template</Link>
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {filteredProjects.length === 0 && (
                                <tr>
                                    <td colSpan={3} className="p-6 text-center text-muted-foreground">
                                        {projects.length === 0 ? 'Belum ada project aktif.' : 'Tidak ada project yang cocok dengan pencarian.'}
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

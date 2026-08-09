// ==========================================================
// MODUL       : projects/archive
// KLASIFIKASI : UI
// TUJUAN      : Permintaan Boss (2026-08-07) — halaman "Arsip Project" terpisah
//               dari daftar aktif (projects/index.tsx). Daftar project
//               is_archived=true SAJA, dengan tombol "Pulihkan" (restore, BARU --
//               sebelum ini TIDAK ADA jalur membatalkan arsip sama sekali).
//               Search + sort SISI BROWSER, pola SAMA projects/index.tsx.
// DIPANGGIL   : ProjectController::archived()
// MEMANGGIL   : route('projects.index'/'projects.restore')
// DATA MASUK  : projects[] (is_archived=true SAJA, sudah difilter role di server)
// DATA KELUAR : PATCH restore (aksi), navigasi kembali ke daftar aktif
// RISIKO      : Route ini digerbangi can:project.manage (routes/admin.php) --
//               HANYA gating tampilan (F-90), penegakan asli di middleware.
// ==========================================================

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { confirmAction } from '@/lib/swal';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface ProjectRow {
    id: number;
    name: string;
    description: string | null;
    owner: { id: number; name: string } | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Project', href: '/projects' },
    { title: 'Arsip', href: '/projects/archive' },
];

type SortKey = 'name' | 'owner';

export default function ProjectsArchive({ projects }: { projects: ProjectRow[] }) {
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

    const restore = async (project: ProjectRow) => {
        if (await confirmAction(`Pulihkan project "${project.name}"? Project akan aktif kembali dan muncul di daftar Project.`)) {
            router.patch(route('projects.restore', project.id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Arsip Project" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">Arsip Project</h1>
                    <Button variant="outline" asChild>
                        <Link href={route('projects.index')}>← Kembali ke Project</Link>
                    </Button>
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
                                <th className="p-3">Status</th>
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
                                        <Badge variant="secondary">Diarsipkan</Badge>
                                    </td>
                                    <td className="p-3">
                                        <Button variant="outline" size="sm" onClick={() => restore(project)}>
                                            Pulihkan
                                        </Button>
                                    </td>
                                </tr>
                            ))}

                            {filteredProjects.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="p-6 text-center text-muted-foreground">
                                        {projects.length === 0 ? 'Belum ada project yang diarsipkan.' : 'Tidak ada project yang cocok dengan pencarian.'}
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

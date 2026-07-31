// ==========================================================
// MODUL       : projects/index
// KLASIFIKASI : UI
// TUJUAN      : Daftar project — admin lihat semua, member hanya yang di-assign
//               (filter sudah dilakukan server-side di ProjectController::index()).
// DIPANGGIL   : ProjectController::index()
// MEMANGGIL   : route('projects.create'/'projects.edit'/'projects.archive')
// DATA MASUK  : projects[] (sudah difilter sesuai role)
// DATA KELUAR : navigasi ke create/edit, PATCH archive
// RISIKO      : F-90 — tombol digating PER PERMISSION (project.manage vs
//               status.manage), bukan satu boolean isAdmin. HANYA gating
//               tampilan — penegakan asli tetap di middleware `can:xxx` server.
// ==========================================================

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';

interface ProjectRow {
    id: number;
    name: string;
    description: string | null;
    is_archived: boolean;
    owner: { id: number; name: string } | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Project', href: '/projects' }];

export default function ProjectsIndex({ projects }: { projects: ProjectRow[] }) {
    const { auth } = usePage<SharedData>().props;
    const can = (permission: string) => auth.permissions.includes(permission);

    const archive = (project: ProjectRow) => {
        if (confirm(`Arsipkan project "${project.name}"? Project yang diarsipkan tidak muncul lagi di daftar aktif.`)) {
            router.patch(route('projects.archive', project.id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Project" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Project</h1>
                    {can('project.manage') && (
                        <Button asChild>
                            <Link href={route('projects.create')}>Project Baru</Link>
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-muted-foreground">
                                <th className="p-3">Nama</th>
                                <th className="p-3">Owner</th>
                                <th className="p-3">Status</th>
                                <th className="p-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {projects.map((project) => (
                                <tr key={project.id} className="border-b last:border-0">
                                    <td className="p-3">
                                        <div className="font-medium">{project.name}</div>
                                        {project.description && <div className="text-muted-foreground">{project.description}</div>}
                                    </td>
                                    <td className="p-3">{project.owner?.name ?? '-'}</td>
                                    <td className="p-3">
                                        {project.is_archived ? <Badge variant="secondary">Diarsipkan</Badge> : <Badge>Aktif</Badge>}
                                    </td>
                                    <td className="p-3">
                                        <div className="flex gap-2">
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={route('tasks.index', project.id)}>Task</Link>
                                            </Button>
                                            {can('project.manage') && (
                                                <>
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={route('projects.edit', project.id)}>Edit</Link>
                                                    </Button>
                                                    {!project.is_archived && (
                                                        <Button variant="outline" size="sm" onClick={() => archive(project)}>
                                                            Arsipkan
                                                        </Button>
                                                    )}
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

                            {projects.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="p-6 text-center text-muted-foreground">
                                        Belum ada project.
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

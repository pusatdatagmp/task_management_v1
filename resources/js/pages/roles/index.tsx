// ==========================================================
// MODUL       : roles/index
// KLASIFIKASI : UI
// TUJUAN      : Daftar role RBAC (RBAC §E1) — tandai role sistem (tidak bisa
//               dihapus/rename), tandai role default (E3), aksi buat/edit/hapus.
// DIPANGGIL   : RoleController::index()
// MEMANGGIL   : route('roles.create'/'roles.edit'/'roles.destroy'/'roles.set-default')
// DATA MASUK  : roles[] (organisasi ini, dengan users_count)
// DATA KELUAR : navigasi create/edit, DELETE, PATCH set-default
// RISIKO      : Tombol Hapus HANYA gating tampilan (disabled untuk is_system/masih
//               dipakai user) — penegakan asli tetap di RoleController::destroy().
// ==========================================================

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface RoleRow {
    id: number;
    role_name: string;
    is_system: boolean;
    is_default: boolean;
    users_count: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'User', href: '/pengaturan/users' },
    { title: 'Role', href: '/pengaturan/roles' },
];

export default function RolesIndex({ roles }: { roles: RoleRow[] }) {
    const setDefault = (role: RoleRow) => {
        router.patch(route('roles.set-default', role.id), {}, { preserveScroll: true });
    };

    const destroy = (role: RoleRow) => {
        if (!confirm(`Hapus role "${role.role_name}"?`)) return;
        router.delete(route('roles.destroy', role.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Role" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Role</h1>
                    <Button asChild>
                        <Link href={route('roles.create')}>Role Baru</Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-muted-foreground">
                                <th className="p-3">Nama</th>
                                <th className="p-3">Tipe</th>
                                <th className="p-3">Default</th>
                                <th className="p-3">Jumlah user</th>
                                <th className="p-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {roles.map((role) => (
                                <tr key={role.id} className="border-b last:border-0">
                                    <td className="p-3 font-medium">{role.role_name}</td>
                                    <td className="p-3">
                                        {role.is_system ? <Badge variant="secondary">Sistem</Badge> : <Badge variant="outline">Custom</Badge>}
                                    </td>
                                    <td className="p-3">
                                        {role.is_default ? (
                                            <Badge>Default</Badge>
                                        ) : (
                                            <Button type="button" variant="ghost" size="sm" onClick={() => setDefault(role)}>
                                                Jadikan default
                                            </Button>
                                        )}
                                    </td>
                                    <td className="p-3">{role.users_count}</td>
                                    <td className="p-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Button type="button" variant="outline" size="sm" asChild>
                                                <Link href={route('roles.edit', role.id)}>Edit</Link>
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={role.is_system || role.users_count > 0}
                                                title={
                                                    role.is_system
                                                        ? 'Role sistem tidak bisa dihapus'
                                                        : role.users_count > 0
                                                          ? 'Masih dipakai user — pindahkan dulu'
                                                          : undefined
                                                }
                                                onClick={() => destroy(role)}
                                            >
                                                Hapus
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {roles.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="p-6 text-center text-muted-foreground">
                                        Belum ada role.
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

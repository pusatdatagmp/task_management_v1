// ==========================================================
// MODUL       : users/index
// KLASIFIKASI : UI
// TUJUAN      : Halaman gabungan "Pengguna & Peran" (permintaan Boss, ref.
//               docs/task-fixx.html VIEWS.users) — 2 kolom: kiri daftar user
//               (fungsi lengkap CRUD/toggle-active, TIDAK dikurangi dari versi
//               sebelumnya), kanan daftar role (edit/hapus/jadikan-default, REUSE
//               route roles.* yang sudah ada, F-109 -- nol endpoint baru). Satu
//               halaman = satu pandangan lengkap RBAC organisasi, bukan 2 halaman
//               terpisah lagi (roles/index.tsx TETAP hidup sebagai route mandiri,
//               cuma tidak lagi jadi tujuan navigasi utama, lihat RoleController).
// DIPANGGIL   : UserController::index()
// MEMANGGIL   : route('users.create'/'users.edit'/'users.toggle-active'/
//               'roles.create'/'roles.edit'/'roles.destroy'/'roles.set-default')
// DATA MASUK  : users[] (seluruh user 1 organisasi, relasi role sudah di-load),
//               roles[] (seluruh role 1 organisasi, dengan users_count)
// DATA KELUAR : navigasi create/edit, PATCH toggle-active/set-default, DELETE role
// RISIKO      : Tombol nonaktifkan/hapus-role HANYA gating tampilan (disabled utk
//               baris diri sendiri / role sistem / role masih dipakai) — penegakan
//               asli tetap di UserController::toggleActive()/RoleController::destroy().
// ==========================================================

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { confirmAction } from '@/lib/swal';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Info } from 'lucide-react';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: { id: number; role_name: string } | null;
    employment_type: 'internal' | 'freelance';
    daily_capacity_minutes: number | null;
    is_active: boolean;
}

interface RoleRow {
    id: number;
    role_name: string;
    is_system: boolean;
    is_default: boolean;
    users_count: number;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pengguna & Peran', href: '/pengaturan/users' }];

interface UsersIndexProps {
    users: UserRow[];
    roles: RoleRow[];
    generatedPassword: string | null;
    generatedPasswordFor: string | null;
}

export default function UsersIndex({ users, roles, generatedPassword, generatedPasswordFor }: UsersIndexProps) {
    const { auth } = usePage<SharedData>().props;

    const toggleActive = async (user: UserRow) => {
        const action = user.is_active ? 'Nonaktifkan' : 'Aktifkan';
        if (!(await confirmAction(`${action} user "${user.name}"?`, { danger: user.is_active }))) return;
        router.patch(route('users.toggle-active', user.id), {}, { preserveScroll: true });
    };

    const setDefaultRole = (role: RoleRow) => {
        router.patch(route('roles.set-default', role.id), {}, { preserveScroll: true });
    };

    const destroyRole = async (role: RoleRow) => {
        if (!(await confirmAction(`Hapus role "${role.role_name}"?`, { danger: true }))) return;
        router.delete(route('roles.destroy', role.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pengguna & Peran" />

            <div className="flex flex-col gap-4 p-4">
                {generatedPassword && (
                    // SUMBER: F-92 — password acak DITAMPILKAN SEKALI dari flash
                    // session (UserController::index()), TIDAK PERNAH disimpan
                    // plaintext. Refresh halaman ini = hilang selamanya (bawaan
                    // Session::flash() Laravel) — admin WAJIB salin sekarang.
                    <div className="rounded-lg border border-amber-500 bg-amber-50 p-4 text-sm dark:bg-amber-950">
                        <p className="font-semibold">
                            User <span className="font-mono">{generatedPasswordFor}</span> dibuat. Salin password ini SEKARANG — tidak akan
                            ditampilkan lagi:
                        </p>
                        <p className="mt-2 font-mono text-base font-bold">{generatedPassword}</p>
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-xl font-semibold">Pengguna & Peran</h1>
                        <p className="text-sm text-muted-foreground">
                            {users.length} pengguna · {roles.length} peran
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('roles.create')}>Peran Baru</Link>
                        </Button>
                        <Button asChild>
                            <Link href={route('users.create')}>Pengguna Baru</Link>
                        </Button>
                    </div>
                </div>

                {/* Permintaan Boss: 2 kolom agar data pengguna & peran langsung
                    terlihat di satu halaman (ref. docs/task-fixx.html VIEWS.users). */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[3fr_2fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Pengguna</CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto p-0">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/50 text-muted-foreground">
                                        <th className="p-3">Nama</th>
                                        <th className="p-3">Email</th>
                                        <th className="p-3">Role</th>
                                        <th className="p-3">Tipe</th>
                                        <th className="p-3">Status</th>
                                        <th className="p-3">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.map((user) => (
                                        <tr key={user.id} className="border-b last:border-0">
                                            <td className="p-3 font-medium">{user.name}</td>
                                            <td className="p-3">{user.email}</td>
                                            <td className="p-3">{user.role?.role_name ?? '-'}</td>
                                            <td className="p-3 capitalize">{user.employment_type}</td>
                                            <td className="p-3">
                                                <Badge variant={user.is_active ? 'default' : 'secondary'}>
                                                    {user.is_active ? 'Aktif' : 'Nonaktif'}
                                                </Badge>
                                            </td>
                                            <td className="p-3">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Button type="button" variant="outline" size="sm" asChild>
                                                        <Link href={route('users.edit', user.id)}>Edit</Link>
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={user.id === auth.user.id}
                                                        title={user.id === auth.user.id ? 'Tidak bisa menonaktifkan akun sendiri' : undefined}
                                                        onClick={() => toggleActive(user)}
                                                    >
                                                        {user.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}

                                    {users.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="p-6 text-center text-muted-foreground">
                                                Belum ada user.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Peran</CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto p-0">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/50 text-muted-foreground">
                                        <th className="p-3">Nama</th>
                                        <th className="p-3">Tipe</th>
                                        <th className="p-3">Default</th>
                                        <th className="p-3">User</th>
                                        <th className="p-3">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {roles.map((role) => (
                                        <tr key={role.id} className="border-b last:border-0">
                                            <td className="p-3 font-medium">{role.role_name}</td>
                                            <td className="p-3">
                                                {role.is_system ? (
                                                    <Badge variant="secondary">Sistem</Badge>
                                                ) : (
                                                    <Badge variant="outline">Custom</Badge>
                                                )}
                                            </td>
                                            <td className="p-3">
                                                {role.is_default ? (
                                                    <Badge>Default</Badge>
                                                ) : (
                                                    <Button type="button" variant="ghost" size="sm" onClick={() => setDefaultRole(role)}>
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
                                                        onClick={() => destroyRole(role)}
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
                        </CardContent>
                    </Card>
                </div>

                <div className="flex items-start gap-2 rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground">
                    <Info className="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
                    <p>
                        <span className="font-medium text-foreground">Peran dinamis:</span> peran & izin tersimpan sebagai data, bukan kode.
                        Menambah peran baru cukup satu klik — tidak perlu deploy ulang. Peran sistem (admin/member) tidak bisa dihapus.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}

// ==========================================================
// MODUL       : users/index
// KLASIFIKASI : UI
// TUJUAN      : Daftar user/member (admin only, F-29). Satu-satunya jalur menambah
//               anggota tim lewat aplikasi — self-signup dimatikan (03-BUSINESS-FLOW §7).
// DIPANGGIL   : UserController::index()
// MEMANGGIL   : route('users.create'/'users.edit'/'users.toggle-active'/'roles.index')
// DATA MASUK  : users[] (seluruh user 1 organisasi, relasi role sudah di-load)
// DATA KELUAR : navigasi create/edit, PATCH toggle-active
// RISIKO      : Tombol nonaktifkan HANYA gating tampilan (disabled untuk baris diri
//               sendiri) — penegakan asli tetap di UserController::toggleActive()
//               (guard admin tidak bisa nonaktifkan akun sendiri).
// ==========================================================

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: { id: number; role_name: string } | null;
    employment_type: 'internal' | 'freelance';
    daily_capacity_minutes: number | null;
    is_active: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'User', href: '/pengaturan/users' }];

interface UsersIndexProps {
    users: UserRow[];
    generatedPassword: string | null;
    generatedPasswordFor: string | null;
}

export default function UsersIndex({ users, generatedPassword, generatedPasswordFor }: UsersIndexProps) {
    const { auth } = usePage<SharedData>().props;

    const toggleActive = (user: UserRow) => {
        const action = user.is_active ? 'Nonaktifkan' : 'Aktifkan';
        if (!confirm(`${action} user "${user.name}"?`)) return;
        router.patch(route('users.toggle-active', user.id), {}, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User" />

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

                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">User</h1>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('roles.index')}>Kelola Role</Link>
                        </Button>
                        <Button asChild>
                            <Link href={route('users.create')}>User Baru</Link>
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-lg border">
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
                </div>
            </div>
        </AppLayout>
    );
}

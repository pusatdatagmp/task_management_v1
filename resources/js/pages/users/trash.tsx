// ==========================================================
// MODUL       : users/trash
// KLASIFIKASI : UI
// TUJUAN      : Halaman "Sampah User" (permintaan Boss 2026-09-11, fitur Hapus
//               Akun) — daftar user deleted_at IS NOT NULL, dengan tombol
//               "Pulihkan" (restore, satu-satunya jalan membatalkan hapus).
//               Pola SAMA projects/archive.tsx.
// DIPANGGIL   : UserController::trashed()
// MEMANGGIL   : route('users.index'/'users.restore')
// DATA MASUK  : users[] (deleted_at IS NOT NULL SAJA, sudah difilter server)
// DATA KELUAR : PATCH restore (aksi), navigasi kembali ke daftar aktif
// RISIKO      : Route ini digerbangi can:user.manage (routes/admin.php) --
//               HANYA gating tampilan (F-90), penegakan asli di middleware.
// ==========================================================

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { confirmAction } from '@/lib/swal';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface TrashedUserRow {
    id: number;
    name: string;
    email: string;
    role: { id: number; role_name: string } | null;
    deleted_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pengguna & Peran', href: '/pengaturan/users' },
    { title: 'Sampah', href: '/pengaturan/users/trash' },
];

// SUMBER: date dikirim backend sebagai ISO datetime lengkap (SerializesDatesInAppTimezone,
// F-72), tabel ini cuma perlu bagian tanggalnya.
function formatDate(isoDateTime: string): string {
    return isoDateTime.slice(0, 10);
}

export default function UsersTrash({ users }: { users: TrashedUserRow[] }) {
    const restore = async (user: TrashedUserRow) => {
        if (await confirmAction(`Pulihkan user "${user.name}"? User akan aktif kembali dan muncul di daftar Pengguna.`)) {
            router.patch(route('users.restore', user.id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sampah User" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">Sampah User</h1>
                    <Button variant="outline" asChild>
                        <Link href={route('users.index')}>← Kembali ke Pengguna & Peran</Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="bg-muted/50 text-muted-foreground border-b">
                                <th className="p-3">Nama</th>
                                <th className="p-3">Email</th>
                                <th className="p-3">Role</th>
                                <th className="p-3">Dihapus pada</th>
                                <th className="p-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr key={user.id} className="border-b last:border-0">
                                    <td className="p-3 font-medium">{user.name}</td>
                                    <td className="p-3">{user.email}</td>
                                    <td className="p-3">{user.role?.role_name ?? '-'}</td>
                                    <td className="p-3">{formatDate(user.deleted_at)}</td>
                                    <td className="p-3">
                                        <Button variant="outline" size="sm" onClick={() => restore(user)}>
                                            Pulihkan
                                        </Button>
                                    </td>
                                </tr>
                            ))}

                            {users.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground p-6 text-center">
                                        Sampah kosong.
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

// ==========================================================
// MODUL       : roles/create
// KLASIFIKASI : UI
// TUJUAN      : Form buat role baru (RBAC §E1) — nama + centang permission per
//               module. SELALU role custom (is_system=false, server-side).
// DIPANGGIL   : RoleController::create()
// MEMANGGIL   : route('roles.store')
// DATA MASUK  : permissions[] (katalog global, dikelompokkan per module)
// DATA KELUAR : POST form -> RoleController::store() -> UserService::createRole()
// RISIKO      : -
// ==========================================================

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface PermissionOption {
    id: number;
    permission_name: string;
    module: string;
}

// Permintaan Boss: halaman Pengguna & Peran digabung 2-kolom (users/index.tsx) --
// node 'Role' yang dulu mengarah ke /pengaturan/roles (kini tanpa link sidebar,
// F-16-style: route TETAP hidup, cuma tidak lagi jadi hub navigasi) DIHAPUS dari
// breadcrumb supaya tidak ada link ke halaman yang terasa "terdampar".
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pengguna & Peran', href: '/pengaturan/users' },
    { title: 'Role Baru', href: '/pengaturan/roles/create' },
];

function groupByModule(permissions: PermissionOption[]): Record<string, PermissionOption[]> {
    return permissions.reduce<Record<string, PermissionOption[]>>((groups, permission) => {
        (groups[permission.module] ??= []).push(permission);
        return groups;
    }, {});
}

export default function RoleCreate({ permissions }: { permissions: PermissionOption[] }) {
    const permissionsByModule = groupByModule(permissions);

    const { data, setData, post, processing, errors } = useForm({
        role_name: '',
        permissions: [] as number[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('roles.store'));
    };

    const togglePermission = (id: number, checked: boolean) => {
        setData('permissions', checked ? [...data.permissions, id] : data.permissions.filter((p) => p !== id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Role Baru" />

            <div className="p-4">
                <Card className="max-w-xl">
                    <CardHeader>
                        <CardTitle>Role Baru</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="role_name">Nama role</Label>
                                <Input
                                    id="role_name"
                                    value={data.role_name}
                                    onChange={(e) => setData('role_name', e.target.value)}
                                    required
                                />
                                <InputError message={errors.role_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Permission</Label>
                                <div className="grid max-h-96 gap-4 overflow-y-auto rounded-md border p-3">
                                    {Object.entries(permissionsByModule).map(([module, perms]) => (
                                        <div key={module} className="grid gap-1">
                                            <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{module}</span>
                                            {perms.map((permission) => (
                                                <label key={permission.id} className="flex items-center gap-2 text-sm">
                                                    <Checkbox
                                                        checked={data.permissions.includes(permission.id)}
                                                        onCheckedChange={(checked) => togglePermission(permission.id, checked === true)}
                                                    />
                                                    {permission.permission_name}
                                                </label>
                                            ))}
                                        </div>
                                    ))}
                                </div>
                                <InputError message={errors.permissions} />
                            </div>

                            <Button disabled={processing}>Simpan</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

// ==========================================================
// MODUL       : roles/edit
// KLASIFIKASI : UI
// TUJUAN      : Form edit role (RBAC §E1) — nama HANYA untuk role custom (role
//               sistem read-only, "tidak bisa dihapus/rename"), permission bisa
//               diedit untuk KEDUA jenis, dengan lantai minimum untuk role sistem
//               (checkbox user.manage disabled kalau ini pemegang terakhir —
//               dijelaskan LANGSUNG di UI, bukan cuma ditolak diam-diam server).
// DIPANGGIL   : RoleController::edit()
// MEMANGGIL   : route('roles.update')
// DATA MASUK  : role (existing), permissionIds[] (yang dimiliki role ini),
//               permissions[] (katalog global), isLastUserManageHolder
// DATA KELUAR : PUT form -> RoleController::update()
// RISIKO      : Guard isLastUserManageHolder di sini HANYA gating tampilan —
//               penegakan asli tetap Role::wouldLeaveNoHolderOfPermission() di
//               server (RoleController::update()), supaya tidak lomba dengan
//               role lain yang diedit bersamaan di tab lain.
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

interface RoleData {
    id: number;
    role_name: string;
    is_system: boolean;
    is_default: boolean;
}

interface PermissionOption {
    id: number;
    permission_name: string;
    module: string;
}

interface RoleEditProps {
    role: RoleData;
    permissionIds: number[];
    permissions: PermissionOption[];
    isLastUserManageHolder: boolean;
}

// Permintaan Boss: lihat catatan sama di roles/create.tsx -- node 'Role' dihapus
// dari breadcrumb sejak halaman Pengguna & Peran digabung 2-kolom.
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pengguna & Peran', href: '/pengaturan/users' },
    { title: 'Edit Role', href: '#' },
];

function groupByModule(permissions: PermissionOption[]): Record<string, PermissionOption[]> {
    return permissions.reduce<Record<string, PermissionOption[]>>((groups, permission) => {
        (groups[permission.module] ??= []).push(permission);
        return groups;
    }, {});
}

export default function RoleEdit({ role, permissionIds, permissions, isLastUserManageHolder }: RoleEditProps) {
    const permissionsByModule = groupByModule(permissions);

    const { data, setData, put, processing, errors } = useForm({
        role_name: role.role_name,
        permissions: permissionIds,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('roles.update', role.id));
    };

    const togglePermission = (id: number, checked: boolean) => {
        setData('permissions', checked ? [...data.permissions, id] : data.permissions.filter((p) => p !== id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${role.role_name}`} />

            <div className="p-4">
                <Card className="max-w-xl">
                    <CardHeader>
                        <CardTitle>Edit Role</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="role_name">Nama role</Label>
                                <Input
                                    id="role_name"
                                    value={data.role_name}
                                    onChange={(e) => setData('role_name', e.target.value)}
                                    disabled={role.is_system}
                                    required={!role.is_system}
                                />
                                {role.is_system && <p className="text-xs text-muted-foreground">Role sistem tidak bisa di-rename.</p>}
                                <InputError message={errors.role_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Permission</Label>
                                <div className="grid max-h-96 gap-4 overflow-y-auto rounded-md border p-3">
                                    {Object.entries(permissionsByModule).map(([module, perms]) => (
                                        <div key={module} className="grid gap-1">
                                            <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{module}</span>
                                            {perms.map((permission) => {
                                                const isLockedUserManage = isLastUserManageHolder && permission.permission_name === 'user.manage';

                                                return (
                                                    <label key={permission.id} className="flex items-center gap-2 text-sm">
                                                        <Checkbox
                                                            checked={data.permissions.includes(permission.id)}
                                                            disabled={isLockedUserManage}
                                                            onCheckedChange={(checked) => togglePermission(permission.id, checked === true)}
                                                        />
                                                        {permission.permission_name}
                                                        {isLockedUserManage && (
                                                            <span className="text-xs text-muted-foreground">
                                                                (tidak bisa dilepas — satu-satunya role pengelola user/role)
                                                            </span>
                                                        )}
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    ))}
                                </div>
                                <InputError message={errors.permissions} />
                            </div>

                            <Button disabled={processing}>Simpan Perubahan</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

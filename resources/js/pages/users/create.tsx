// ==========================================================
// MODUL       : users/create
// KLASIFIKASI : UI
// TUJUAN      : Form onboarding user baru (RBAC §C4/E2). Password TIDAK diketik
//               admin (F-92) — dibuat acak di server, ditampilkan SEKALI di
//               users/index.tsx setelah redirect. Tepat SATU dari 3 mode role
//               boleh dipilih — RADIO (F-74-style), bukan checkbox, supaya
//               kombinasi payload tidak valid secara struktural tidak mungkin.
// DIPANGGIL   : UserController::create()
// MEMANGGIL   : route('users.store')
// DATA MASUK  : roles[] (mode "role eksisting"/"clone"), permissions[] (mode
//               "clone"/"role baru", dikelompokkan per module)
// DATA KELUAR : POST form -> UserController::store() -> UserService::onboardNewUser()
// RISIKO      : -
// ==========================================================

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface RoleOption {
    id: number;
    role_name: string;
    is_system: boolean;
}

interface PermissionOption {
    id: number;
    permission_name: string;
    module: string;
}

interface UserCreateProps {
    roles: RoleOption[];
    permissions: PermissionOption[];
}

type RoleMode = 'existing' | 'clone' | 'new';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'User', href: '/pengaturan/users' },
    { title: 'User Baru', href: '/pengaturan/users/create' },
];

function groupByModule(permissions: PermissionOption[]): Record<string, PermissionOption[]> {
    return permissions.reduce<Record<string, PermissionOption[]>>((groups, permission) => {
        (groups[permission.module] ??= []).push(permission);
        return groups;
    }, {});
}

export default function UserCreate({ roles, permissions }: UserCreateProps) {
    const permissionsByModule = groupByModule(permissions);

    const { data, setData, post, transform, processing, errors } = useForm({
        name: '',
        email: '',
        employment_type: 'internal',
        daily_capacity_minutes: '' as number | '',
        role_mode: 'existing' as RoleMode,
        role_id: (roles[0]?.id ?? '') as number | '',
        base_role_id: '' as number | '',
        new_role_name: '',
        custom_permissions: [] as number[],
        permissions: [] as number[],
    });

    // SUMBER: OnboardUserRequest::withValidator() menentukan mode dari field mana
    // yang TERISI (F-74-style — exactly-one). useForm menyimpan SEMUA field
    // sekaligus (termasuk role_id yang ter-default ke roles[0] walau mode aktif
    // "clone"/"new") — transform() membuang field di luar mode aktif SEBELUM
    // dikirim, supaya server hanya melihat SATU mode terisi, bukan tebak-tebak.
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        transform((formData) => {
            if (formData.role_mode === 'existing') {
                return { ...formData, base_role_id: '', new_role_name: '', custom_permissions: [], permissions: [] };
            }
            if (formData.role_mode === 'clone') {
                return { ...formData, role_id: '', permissions: [] };
            }
            return { ...formData, role_id: '', base_role_id: '', custom_permissions: [] };
        });
        post(route('users.store'));
    };

    const togglePermission = (field: 'custom_permissions' | 'permissions', id: number, checked: boolean) => {
        setData(field, checked ? [...data[field], id] : data[field].filter((p) => p !== id));
    };

    const permissionChecklist = (field: 'custom_permissions' | 'permissions') => (
        <div className="grid max-h-64 gap-4 overflow-y-auto rounded-md border p-3">
            {Object.entries(permissionsByModule).map(([module, perms]) => (
                <div key={module} className="grid gap-1">
                    <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{module}</span>
                    {perms.map((permission) => (
                        <label key={permission.id} className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={data[field].includes(permission.id)}
                                onCheckedChange={(checked) => togglePermission(field, permission.id, checked === true)}
                            />
                            {permission.permission_name}
                        </label>
                    ))}
                </div>
            ))}
        </div>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Baru" />

            <div className="p-4">
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>User Baru</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nama</Label>
                                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    required
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="employment_type">Tipe</Label>
                                    <Select value={data.employment_type} onValueChange={(value) => setData('employment_type', value)}>
                                        <SelectTrigger id="employment_type">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="internal">Internal</SelectItem>
                                            <SelectItem value="freelance">Freelance</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.employment_type} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="daily_capacity_minutes">Kapasitas harian (menit, opsional)</Label>
                                    <Input
                                        id="daily_capacity_minutes"
                                        type="number"
                                        min={1}
                                        value={data.daily_capacity_minutes}
                                        onChange={(e) => setData('daily_capacity_minutes', e.target.value === '' ? '' : Number(e.target.value))}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-3 border-t pt-4">
                                <Label>Role</Label>

                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="radio"
                                        name="role_mode"
                                        checked={data.role_mode === 'existing'}
                                        onChange={() => setData('role_mode', 'existing')}
                                    />
                                    Pakai role yang sudah ada
                                </label>
                                {data.role_mode === 'existing' && (
                                    <Select value={String(data.role_id)} onValueChange={(value) => setData('role_id', Number(value))}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih role" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roles.map((role) => (
                                                <SelectItem key={role.id} value={String(role.id)}>
                                                    {role.role_name}
                                                    {role.is_system ? ' (sistem)' : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}

                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="radio"
                                        name="role_mode"
                                        checked={data.role_mode === 'clone'}
                                        onChange={() => setData('role_mode', 'clone')}
                                    />
                                    Clone dari role, lalu sesuaikan permission
                                </label>
                                {data.role_mode === 'clone' && (
                                    <div className="grid gap-3 pl-2">
                                        <Select value={String(data.base_role_id)} onValueChange={(value) => setData('base_role_id', Number(value))}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Clone dari role mana?" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {roles.map((role) => (
                                                    <SelectItem key={role.id} value={String(role.id)}>
                                                        {role.role_name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Input
                                            placeholder="Nama role baru"
                                            value={data.new_role_name}
                                            onChange={(e) => setData('new_role_name', e.target.value)}
                                        />
                                        {permissionChecklist('custom_permissions')}
                                    </div>
                                )}

                                <label className="flex items-center gap-2 text-sm">
                                    <input type="radio" name="role_mode" checked={data.role_mode === 'new'} onChange={() => setData('role_mode', 'new')} />
                                    Role baru dari kosong
                                </label>
                                {data.role_mode === 'new' && (
                                    <div className="grid gap-3 pl-2">
                                        <Input
                                            placeholder="Nama role baru"
                                            value={data.new_role_name}
                                            onChange={(e) => setData('new_role_name', e.target.value)}
                                        />
                                        {permissionChecklist('permissions')}
                                    </div>
                                )}

                                <InputError
                                    message={
                                        (errors as Record<string, string>).role ?? errors.role_id ?? errors.new_role_name ?? errors.permissions
                                    }
                                />
                            </div>

                            <Button disabled={processing}>Simpan</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

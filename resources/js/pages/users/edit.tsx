// ==========================================================
// MODUL       : users/edit
// KLASIFIKASI : UI
// TUJUAN      : Form edit user/member (permission user.manage, F-90). Password
//               OPSIONAL — kosong berarti password lama tetap dipakai (lihat
//               UpdateUserRequest). role_id HANYA assign role EKSISTING — buat
//               role baru lewat halaman Kelola Role (roles/create), bukan di sini.
// DIPANGGIL   : UserController::edit()
// MEMANGGIL   : route('users.update')
// DATA MASUK  : user (existing), roles[] (daftar role organisasi ini)
// DATA KELUAR : PUT form -> UserController::update()
// RISIKO      : is_active SENGAJA TIDAK ADA di form ini — nonaktifkan/aktifkan
//               lewat tombol terpisah di users/index.tsx (action eksplisit, bukan
//               field yang bisa ke-toggle tanpa sengaja saat edit field lain).
// ==========================================================

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface UserData {
    id: number;
    name: string;
    email: string;
    role_id: number;
    employment_type: string;
    daily_capacity_minutes: number | null;
}

interface RoleOption {
    id: number;
    role_name: string;
    is_system: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'User', href: '/pengaturan/users' },
    { title: 'Edit User', href: '#' },
];

export default function UserEdit({ user, roles }: { user: UserData; roles: RoleOption[] }) {
    const { data, setData, put, processing, errors } = useForm({
        name: user.name,
        email: user.email,
        password: '',
        password_confirmation: '',
        role_id: user.role_id,
        employment_type: user.employment_type,
        daily_capacity_minutes: (user.daily_capacity_minutes ?? '') as number | '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('users.update', user.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${user.name}`} />

            <div className="p-4">
                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle>Edit User</CardTitle>
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

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password baru (opsional)</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        placeholder="Kosongkan kalau tidak ganti"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">Konfirmasi Password</Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="role_id">Role</Label>
                                    <Select value={String(data.role_id)} onValueChange={(value) => setData('role_id', Number(value))}>
                                        <SelectTrigger id="role_id">
                                            <SelectValue />
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
                                    <InputError message={errors.role_id} />
                                </div>

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
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="daily_capacity_minutes">Kapasitas harian (menit, opsional)</Label>
                                <Input
                                    id="daily_capacity_minutes"
                                    type="number"
                                    min={1}
                                    placeholder="Kosongkan untuk pakai default jam kerja organisasi"
                                    value={data.daily_capacity_minutes}
                                    onChange={(e) => setData('daily_capacity_minutes', e.target.value === '' ? '' : Number(e.target.value))}
                                />
                                <InputError message={errors.daily_capacity_minutes} />
                            </div>

                            <Button disabled={processing}>Simpan Perubahan</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

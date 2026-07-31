// ==========================================================
// MODUL       : projects/create
// KLASIFIKASI : UI
// TUJUAN      : Form buat project baru. Owner wajib pilih 1, member multi-select
//               opsional (owner otomatis ikut jadi member di server, lihat
//               ProjectController::store()).
// DIPANGGIL   : ProjectController::create()
// MEMANGGIL   : route('projects.store')
// DATA MASUK  : users[] (dropdown owner + checklist member)
// DATA KELUAR : POST form -> ProjectController::store()
// RISIKO      : -
// ==========================================================

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface UserOption {
    id: number;
    name: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Project', href: '/projects' },
    { title: 'Project Baru', href: '/projects/create' },
];

export default function ProjectCreate({ users }: { users: UserOption[] }) {
    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        description: string;
        owner_id: number | '';
        members: number[];
    }>({
        name: '',
        description: '',
        owner_id: '',
        members: [],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('projects.store'));
    };

    const toggleMember = (id: number, checked: boolean) => {
        setData('members', checked ? [...data.members, id] : data.members.filter((m) => m !== id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Project Baru" />

            <div className="p-4">
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Project Baru</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nama project</Label>
                                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Deskripsi</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="owner_id">Owner (reviewer, F-28)</Label>
                                <Select
                                    value={data.owner_id ? String(data.owner_id) : undefined}
                                    onValueChange={(value) => setData('owner_id', Number(value))}
                                >
                                    <SelectTrigger id="owner_id">
                                        <SelectValue placeholder="Pilih owner" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {users.map((user) => (
                                            <SelectItem key={user.id} value={String(user.id)}>
                                                {user.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.owner_id} />
                            </div>

                            <div className="grid gap-2">
                                <HeadingSmall title="Member" description="Owner otomatis ikut jadi member" />
                                <div className="grid max-h-48 grid-cols-2 gap-2 overflow-y-auto rounded-md border p-3">
                                    {users.map((user) => (
                                        <label key={user.id} className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={data.members.includes(user.id)}
                                                onCheckedChange={(checked) => toggleMember(user.id, checked === true)}
                                            />
                                            {user.name}
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.members} />
                            </div>

                            <Button disabled={processing}>Simpan</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

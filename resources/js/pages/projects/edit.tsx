// ==========================================================
// MODUL       : projects/edit
// KLASIFIKASI : UI
// TUJUAN      : Form edit project — name/description/owner/member. Archive punya
//               tombol terpisah di halaman index (bukan di sini).
// DIPANGGIL   : ProjectController::edit()
// MEMANGGIL   : route('projects.update')
// DATA MASUK  : project (data existing), users[], memberIds[] (member saat ini)
// DATA KELUAR : PUT form -> ProjectController::update()
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

interface ProjectData {
    id: number;
    name: string;
    description: string | null;
    owner_id: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Project', href: '/projects' },
    { title: 'Edit Project', href: '#' },
];

export default function ProjectEdit({ project, users, memberIds }: { project: ProjectData; users: UserOption[]; memberIds: number[] }) {
    const { data, setData, put, processing, errors } = useForm<{
        name: string;
        description: string;
        owner_id: number | '';
        members: number[];
    }>({
        name: project.name,
        description: project.description ?? '',
        owner_id: project.owner_id,
        members: memberIds,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('projects.update', project.id));
    };

    const toggleMember = (id: number, checked: boolean) => {
        setData('members', checked ? [...data.members, id] : data.members.filter((m) => m !== id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${project.name}`} />

            <div className="p-4">
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Edit Project</CardTitle>
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

                            <Button disabled={processing}>Simpan Perubahan</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

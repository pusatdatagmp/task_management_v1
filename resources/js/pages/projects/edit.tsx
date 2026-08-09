// ==========================================================
// MODUL       : projects/edit
// KLASIFIKASI : UI
// TUJUAN      : Form edit project — name/description/owner/member. Archive punya
//               tombol terpisah di halaman index (bukan di sini).
//               Revisi 2026-08-07 (permintaan Boss): checklist Owner HANYA user
//               ber-permission project.manage -- `owners` prop TERPISAH dari
//               `users` (checklist Member TETAP semua user). `owners` dari
//               server SELALU menyertakan owner SAAT INI walau permission-nya
//               sudah dicabut (ProjectController::eligibleOwners()) supaya
//               checklist tidak kehilangan opsi untuk value yang valid.
//               2026-08-08 (keputusan Boss): Owner boleh >1, urutan CENTANG
//               menentukan Owner "Utama" (posisi-0) -- pola sama create.tsx.
// DIPANGGIL   : ProjectController::edit()
// MEMANGGIL   : route('projects.update')
// DATA MASUK  : project (data existing), users[] (checklist member),
//               owners[] (checklist owner), memberIds[] (member saat ini),
//               ownerIds[] (owner saat ini, terurut posisi -- index 0 = utama)
// DATA KELUAR : PUT form -> ProjectController::update()
// RISIKO      : -
// ==========================================================

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Project', href: '/projects' },
    { title: 'Edit Project', href: '#' },
];

export default function ProjectEdit({
    project,
    users,
    owners,
    memberIds,
    ownerIds,
}: {
    project: ProjectData;
    users: UserOption[];
    owners: UserOption[];
    memberIds: number[];
    ownerIds: number[];
}) {
    const { data, setData, put, processing, errors } = useForm<{
        name: string;
        description: string;
        owner_ids: number[];
        members: number[];
    }>({
        name: project.name,
        description: project.description ?? '',
        owner_ids: ownerIds,
        members: memberIds,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('projects.update', project.id));
    };

    const toggleOwner = (id: number, checked: boolean) => {
        setData('owner_ids', checked ? [...data.owner_ids, id] : data.owner_ids.filter((o) => o !== id));
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
                                <HeadingSmall
                                    title="Owner (reviewer, F-28)"
                                    description="Boleh lebih dari satu -- yang dicentang PERTAMA jadi Owner Utama"
                                />
                                <div className="grid max-h-48 grid-cols-1 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                                    {owners.map((user) => (
                                        <label key={user.id} className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={data.owner_ids.includes(user.id)}
                                                onCheckedChange={(checked) => toggleOwner(user.id, checked === true)}
                                            />
                                            {user.name}
                                        </label>
                                    ))}
                                </div>
                                {data.owner_ids.length > 0 && (
                                    <div className="flex flex-wrap items-center gap-1.5">
                                        {data.owner_ids.map((id, index) => (
                                            <Badge key={id} variant={index === 0 ? 'default' : 'outline'}>
                                                {owners.find((o) => o.id === id)?.name}
                                                {index === 0 && ' (Utama)'}
                                            </Badge>
                                        ))}
                                    </div>
                                )}
                                <InputError message={errors.owner_ids} />
                            </div>

                            <div className="grid gap-2">
                                <HeadingSmall title="Member" description="Owner otomatis ikut jadi member" />
                                <div className="grid max-h-48 grid-cols-1 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
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

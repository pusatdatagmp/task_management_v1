// ==========================================================
// MODUL       : projects/create
// KLASIFIKASI : UI
// TUJUAN      : Form buat project baru. Owner wajib pilih >=1 (2026-08-08,
//               keputusan Boss: boleh lebih dari satu), member multi-select
//               opsional (SEMUA owner otomatis ikut jadi member di server,
//               lihat ProjectController::store()).
//               Revisi 2026-08-07 (permintaan Boss): checklist Owner HANYA user
//               ber-permission project.manage ("admin", bukan member biasa) --
//               `owners` prop TERPISAH dari `users` (checklist Member TETAP
//               semua user, cuma Owner yang dibatasi). Ditegakkan juga di
//               server (StoreProjectRequest) — ini HANYA gating tampilan (F-90).
//               2026-08-08: urutan CENTANG menentukan Owner "Utama" (posisi-0,
//               dicerminkan ke projects.owner_id server-side) -- toggleOwner()
//               SELALU menambah di ujung array, TIDAK PERNAH reorder existing,
//               jadi owner pertama yang masih tercentang otomatis Utama.
// DIPANGGIL   : ProjectController::create()
// MEMANGGIL   : route('projects.store')
// DATA MASUK  : users[] (checklist member, HANYA is_active=true -- revisi
//               2026-08-07), owners[] (checklist owner, subset users aktif)
// DATA KELUAR : POST form -> ProjectController::store()
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Project', href: '/projects' },
    { title: 'Project Baru', href: '/projects/create' },
];

export default function ProjectCreate({ users, owners }: { users: UserOption[]; owners: UserOption[] }) {
    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        description: string;
        owner_ids: number[];
        members: number[];
    }>({
        name: '',
        description: '',
        owner_ids: [],
        members: [],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('projects.store'));
    };

    const toggleOwner = (id: number, checked: boolean) => {
        setData('owner_ids', checked ? [...data.owner_ids, id] : data.owner_ids.filter((o) => o !== id));
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

                            <Button disabled={processing}>Simpan</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

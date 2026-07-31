// ==========================================================
// MODUL       : task-statuses/edit
// KLASIFIKASI : UI
// TUJUAN      : Form rename/ubah warna SATU status (F-44). Flag (F-74) TIDAK di
//               sini lagi — lihat tabel radio di task-statuses/index.
// DIPANGGIL   : TaskStatusController::edit()
// MEMANGGIL   : route('task-statuses.update')
// DATA MASUK  : project {id,name}, status (data existing)
// DATA KELUAR : PUT form -> TaskStatusController::update()
// RISIKO      : -
// ==========================================================

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface StatusData {
    id: number;
    name: string;
    color: string;
}

export default function TaskStatusEdit({ project, status }: { project: { id: number; name: string }; status: StatusData }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Project', href: '/projects' },
        { title: project.name, href: route('projects.edit', project.id) },
        { title: 'Status', href: route('task-statuses.index', project.id) },
        { title: `Edit ${status.name}`, href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm({
        name: status.name,
        color: status.color,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('task-statuses.update', [project.id, status.id]));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${status.name} — ${project.name}`} />

            <div className="p-4">
                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle>Edit Status</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nama status</Label>
                                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="color">Warna</Label>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="color"
                                        className="h-10 w-14 rounded-md border border-input"
                                        value={data.color}
                                        onChange={(e) => setData('color', e.target.value)}
                                    />
                                    <Input value={data.color} onChange={(e) => setData('color', e.target.value)} className="max-w-32" />
                                </div>
                                <InputError message={errors.color} />
                            </div>

                            <p className="text-sm text-muted-foreground">
                                Flag (selesai/review/sedang dikerjakan) diatur di tabel pada halaman daftar status, bukan di sini.
                            </p>

                            <Button disabled={processing}>Simpan Perubahan</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

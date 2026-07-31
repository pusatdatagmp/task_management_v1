// ==========================================================
// MODUL       : task-statuses/create
// KLASIFIKASI : UI
// TUJUAN      : Form tambah status baru — HANYA nama & warna (F-74, Hari-5).
//               Flag (selesai/review/sedang dikerjakan) diatur belakangan di
//               tabel radio halaman index, bukan di sini.
// DIPANGGIL   : TaskStatusController::create()
// MEMANGGIL   : route('task-statuses.store')
// DATA MASUK  : project {id,name}
// DATA KELUAR : POST form -> TaskStatusController::store()
// RISIKO      : Status baru SELALU lahir netral (ketiga flag false, dipaksa di
//               TaskStatusController::store()) — kalau lupa aktifkan is_work_state
//               dkk di halaman index setelah ini, status baru tidak akan pernah
//               dipakai counter/review/selesai sampai diatur manual.
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

export default function TaskStatusCreate({ project }: { project: { id: number; name: string } }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Project', href: '/projects' },
        { title: project.name, href: route('projects.edit', project.id) },
        { title: 'Status', href: route('task-statuses.index', project.id) },
        { title: 'Status Baru', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        color: string;
    }>({
        name: '',
        color: '#94a3b8',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('task-statuses.store', project.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Status Baru — ${project.name}`} />

            <div className="p-4">
                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle>Status Baru</CardTitle>
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
                                Status baru dibuat netral (belum jadi status selesai/review/sedang dikerjakan). Atur itu di tabel flag pada
                                halaman daftar status setelah status ini tersimpan.
                            </p>

                            <Button disabled={processing}>Simpan</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

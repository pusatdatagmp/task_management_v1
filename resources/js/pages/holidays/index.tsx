// ==========================================================
// MODUL       : holidays/index
// KLASIFIKASI : UI
// TUJUAN      : Pengaturan > Hari Libur (F-43) — CRUD sederhana tanggal+nama,
//               dipakai BusinessHoursCalculator untuk skip hari libur dari realisasi.
// DIPANGGIL   : HolidayController::index()
// MEMANGGIL   : route('holidays.store'/'update'/'destroy')
// DATA MASUK  : holidays[] (urut tanggal, dari HolidayController::index())
// DATA KELUAR : POST/PUT/DELETE -> HolidayController
// RISIKO      : Satu <form> dipakai gabungan tambah+edit (editingId null = mode
//               tambah) — tombol Batal WAJIB reset editingId, kalau tidak submit
//               berikutnya diam-diam jadi PUT ke baris yang salah.
// ==========================================================

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { confirmAction } from '@/lib/swal';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface HolidayRow {
    id: number;
    date: string;
    name: string;
}

// SUMBER: date dikirim backend sebagai ISO datetime lengkap (SerializesDatesInAppTimezone,
// F-72), tabel & input type="date" cuma perlu bagian tanggalnya.
function formatDate(isoDateTime: string): string {
    return isoDateTime.slice(0, 10);
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pengaturan Hari Libur', href: '/pengaturan/hari-libur' }];

export default function HolidaysIndex({ holidays }: { holidays: HolidayRow[] }) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const { data, setData, post, put, processing, errors, reset } = useForm<{ date: string; name: string }>({
        date: '',
        name: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (editingId) {
            put(route('holidays.update', editingId), {
                onSuccess: () => {
                    reset();
                    setEditingId(null);
                },
            });
        } else {
            post(route('holidays.store'), {
                onSuccess: () => reset(),
            });
        }
    };

    const startEdit = (holiday: HolidayRow) => {
        setEditingId(holiday.id);
        setData({ date: formatDate(holiday.date), name: holiday.name });
    };

    const cancelEdit = () => {
        setEditingId(null);
        reset();
    };

    const destroy = async (holiday: HolidayRow) => {
        if (await confirmAction(`Hapus hari libur "${holiday.name}" (${formatDate(holiday.date)})?`, { danger: true })) {
            router.delete(route('holidays.destroy', holiday.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pengaturan Hari Libur" />

            <div className="flex flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>{editingId ? 'Ubah Hari Libur' : 'Tambah Hari Libur'}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="date">Tanggal</Label>
                                    <Input id="date" type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} />
                                    <InputError message={errors.date} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nama</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        maxLength={100}
                                        placeholder="mis. Hari Kemerdekaan RI"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                    />
                                    <InputError message={errors.name} />
                                </div>
                            </div>

                            <div className="flex gap-2">
                                <Button disabled={processing}>{editingId ? 'Simpan Perubahan' : 'Tambah'}</Button>
                                {editingId ? (
                                    <Button type="button" variant="outline" onClick={cancelEdit}>
                                        Batal
                                    </Button>
                                ) : null}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <HeadingSmall title="Daftar Hari Libur" description="Urut tanggal — dipakai BusinessHoursCalculator untuk skip realisasi (F-43)" />
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b text-muted-foreground">
                                        <th className="py-2 pr-4">Tanggal</th>
                                        <th className="py-2 pr-4">Nama</th>
                                        <th className="py-2 pr-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {holidays.length === 0 ? (
                                        <tr>
                                            <td colSpan={3} className="py-4 text-center text-muted-foreground">
                                                Belum ada hari libur.
                                            </td>
                                        </tr>
                                    ) : (
                                        holidays.map((holiday) => (
                                            <tr key={holiday.id} className="border-b last:border-0">
                                                <td className="py-2 pr-4">{formatDate(holiday.date)}</td>
                                                <td className="py-2 pr-4">{holiday.name}</td>
                                                <td className="py-2 pr-4">
                                                    <div className="flex gap-2">
                                                        <Button type="button" variant="outline" size="sm" onClick={() => startEdit(holiday)}>
                                                            Edit
                                                        </Button>
                                                        <Button type="button" variant="outline" size="sm" onClick={() => destroy(holiday)}>
                                                            Hapus
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

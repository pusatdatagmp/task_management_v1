// ==========================================================
// MODUL       : work-schedules/index
// KLASIFIKASI : UI
// TUJUAN      : Pengaturan > Jam Kerja — riwayat versi (F-40) + form tambah versi baru.
//               TIDAK ADA edit/delete (B6) — versi lama arsip permanen.
// DIPANGGIL   : WorkScheduleController::index()
// MEMANGGIL   : route('work-schedules.store')
// DATA MASUK  : schedules[] (riwayat, urut effective_from desc), activeId (versi aktif)
// DATA KELUAR : POST form -> WorkScheduleController::store()
// RISIKO      : F-70 — effective_from di form TIDAK punya date picker yang izinkan
//               tanggal lampau; validasi keras tetap di server (FormRequest), ini cuma UX.
// ==========================================================

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface WorkScheduleRow {
    id: number;
    effective_from: string;
    days_of_week: number[];
    start_time: string;
    end_time: string;
    daily_capacity_minutes: number;
    creator: { id: number; name: string } | null;
}

const DAY_LABELS: { value: number; label: string }[] = [
    { value: 1, label: 'Senin' },
    { value: 2, label: 'Selasa' },
    { value: 3, label: 'Rabu' },
    { value: 4, label: 'Kamis' },
    { value: 5, label: 'Jumat' },
    { value: 6, label: 'Sabtu' },
    { value: 7, label: 'Minggu' },
];

function formatDays(days: number[]): string {
    return DAY_LABELS.filter((d) => days.includes(d.value))
        .map((d) => d.label.slice(0, 3))
        .join(', ');
}

// SUMBER: effective_from dikirim backend sebagai ISO datetime lengkap (F-69 —
// serializeDate() WorkSchedule menjaga offset lokal, lihat app/Models/WorkSchedule.php),
// tabel ini cuma perlu bagian tanggalnya.
function formatDate(isoDateTime: string): string {
    return isoDateTime.slice(0, 10);
}

// SUMBER: format HH:MM:SS dari DB (kolom time MySQL) -> HH:MM buat tampilan.
function formatTime(time: string): string {
    return time.slice(0, 5);
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pengaturan Jam Kerja', href: '/pengaturan/jam-kerja' }];

export default function WorkSchedulesIndex({ schedules, activeId }: { schedules: WorkScheduleRow[]; activeId: number | null }) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        days_of_week: number[];
        start_time: string;
        end_time: string;
        daily_capacity_minutes: number;
        effective_from: string;
    }>({
        days_of_week: [1, 2, 3, 4, 5],
        start_time: '08:00',
        end_time: '17:00',
        daily_capacity_minutes: 480,
        effective_from: new Date().toISOString().slice(0, 10),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('work-schedules.store'), {
            onSuccess: () => reset('effective_from'),
        });
    };

    const toggleDay = (value: number, checked: boolean) => {
        setData('days_of_week', checked ? [...data.days_of_week, value].sort() : data.days_of_week.filter((d) => d !== value));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pengaturan Jam Kerja" />

            <div className="flex flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Tambah Versi Jam Kerja</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label>Hari kerja</Label>
                                <div className="flex flex-wrap gap-4">
                                    {DAY_LABELS.map((day) => (
                                        <label key={day.value} className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={data.days_of_week.includes(day.value)}
                                                onCheckedChange={(checked) => toggleDay(day.value, checked === true)}
                                            />
                                            {day.label}
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.days_of_week} />
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="start_time">Jam mulai</Label>
                                    <Input
                                        id="start_time"
                                        type="time"
                                        value={data.start_time}
                                        onChange={(e) => setData('start_time', e.target.value)}
                                    />
                                    <InputError message={errors.start_time} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="end_time">Jam selesai</Label>
                                    <Input id="end_time" type="time" value={data.end_time} onChange={(e) => setData('end_time', e.target.value)} />
                                    <InputError message={errors.end_time} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="daily_capacity_minutes">Kapasitas (menit/hari)</Label>
                                    <Input
                                        id="daily_capacity_minutes"
                                        type="number"
                                        min={1}
                                        value={data.daily_capacity_minutes}
                                        onChange={(e) => setData('daily_capacity_minutes', Number(e.target.value))}
                                    />
                                    <InputError message={errors.daily_capacity_minutes} />
                                </div>
                            </div>

                            <div className="grid gap-2 sm:max-w-xs">
                                <Label htmlFor="effective_from">Berlaku mulai</Label>
                                <Input
                                    id="effective_from"
                                    type="date"
                                    value={data.effective_from}
                                    onChange={(e) => setData('effective_from', e.target.value)}
                                />
                                <InputError message={errors.effective_from} />
                            </div>

                            <Button disabled={processing}>Simpan sebagai versi baru</Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <HeadingSmall title="Riwayat Versi" description="Versi lama tidak bisa diedit/dihapus — arsip permanen (F-40)" />
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b text-muted-foreground">
                                        <th className="py-2 pr-4">Berlaku mulai</th>
                                        <th className="py-2 pr-4">Hari kerja</th>
                                        <th className="py-2 pr-4">Jam</th>
                                        <th className="py-2 pr-4">Kapasitas</th>
                                        <th className="py-2 pr-4">Dibuat oleh</th>
                                        <th className="py-2 pr-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {schedules.map((schedule) => (
                                        <tr key={schedule.id} className="border-b last:border-0">
                                            <td className="py-2 pr-4">{formatDate(schedule.effective_from)}</td>
                                            <td className="py-2 pr-4">{formatDays(schedule.days_of_week)}</td>
                                            <td className="py-2 pr-4">
                                                {formatTime(schedule.start_time)}–{formatTime(schedule.end_time)}
                                            </td>
                                            <td className="py-2 pr-4">{schedule.daily_capacity_minutes} menit</td>
                                            <td className="py-2 pr-4">{schedule.creator?.name ?? '-'}</td>
                                            <td className="py-2 pr-4">
                                                {schedule.id === activeId ? <Badge>Aktif</Badge> : <Badge variant="secondary">Arsip</Badge>}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

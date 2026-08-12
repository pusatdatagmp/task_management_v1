// ==========================================================
// MODUL       : work-schedules/index
// KLASIFIKASI : UI
// TUJUAN      : Pengaturan > Jam Kerja — audit Boss 2026-08-12 (F-169): 1 kartu
//               "Jam Kerja Saat Ini" (bukan tabel riwayat semua versi) + tombol
//               Edit + "Log Perubahan" di bawahnya. F-40 TETAP dihormati penuh:
//               Edit TIDAK update baris aktif (dilarang), tapi POST ke
//               work-schedules.quick-edit -> INSERT versi baru effective_from
//               HARI INI di server (WorkScheduleController::quickEdit()). Fitur
//               penjadwalan versi masa depan (tanggal custom/arsip/"Jadikan Aktif
//               Sekarang") SENGAJA dilepas dari UI ini atas keputusan Boss --
//               backend (store/update/archive/activateNow) tetap utuh, reversible.
// DIPANGGIL   : WorkScheduleController::index()
// MEMANGGIL   : route('work-schedules.quick-edit')
// DATA MASUK  : current (versi WorkSchedule aktif sekarang, null kalau org belum
//               pernah diatur), logs (feed activity_logs subject WorkSchedule,
//               sudah diterjemahkan ActivityLogPresenter di server)
// DATA KELUAR : POST form -> WorkScheduleController::quickEdit()
// RISIKO      : SUMBER guard "sudah diubah hari ini" -- server (quickEdit()) yang
//               menolak edit ke-2 di hari yang sama (F-40, satu effective_from per
//               hari), pesan errornya dirender lewat errors.daily_capacity_minutes
//               (lihat komentar controller) supaya muncul di form, BUKAN toast generik.
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
import { FormEventHandler, useState } from 'react';

interface WorkScheduleRow {
    id: number;
    effective_from: string;
    days_of_week: number[];
    start_time: string;
    end_time: string;
    daily_capacity_minutes: number;
    creator: { id: number; name: string } | null;
}

interface LogEntry {
    id: number;
    actor: string;
    event_label: string;
    message: string;
    created_at: string;
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

// SUMBER: format HH:MM:SS dari DB (kolom time MySQL) -> HH:MM buat tampilan/form.
function formatTime(time: string): string {
    return time.slice(0, 5);
}

// SUMBER: effective_from dikirim backend sebagai ISO datetime (F-69, trait
// SerializesDatesInAppTimezone) -- kartu ini cuma perlu bagian tanggalnya.
function formatDate(isoDateTime: string): string {
    return isoDateTime.slice(0, 10);
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pengaturan Jam Kerja', href: '/pengaturan/jam-kerja' }];

type FormShape = {
    days_of_week: number[];
    start_time: string;
    end_time: string;
    daily_capacity_minutes: number;
};

function toFormShape(current: WorkScheduleRow | null): FormShape {
    if (!current) {
        return { days_of_week: [1, 2, 3, 4, 5], start_time: '08:00', end_time: '17:00', daily_capacity_minutes: 480 };
    }

    return {
        days_of_week: current.days_of_week,
        start_time: formatTime(current.start_time),
        end_time: formatTime(current.end_time),
        daily_capacity_minutes: current.daily_capacity_minutes,
    };
}

export default function WorkSchedulesIndex({ current, logs }: { current: WorkScheduleRow | null; logs: LogEntry[] }) {
    const { data, setData, post, processing, errors, reset } = useForm<FormShape>(toFormShape(current));

    // SUMBER: kartu ringkas cuma 2 mode -- lihat (read-only) atau edit (form).
    // Beda dari halaman lama yang punya form terpisah + tabel riwayat.
    const [isEditing, setIsEditing] = useState(false);

    const startEdit = () => {
        setData(toFormShape(current));
        setIsEditing(true);
    };

    const cancelEdit = () => {
        reset();
        setData(toFormShape(current));
        setIsEditing(false);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('work-schedules.quick-edit'), {
            onSuccess: () => setIsEditing(false),
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
                        <CardTitle>{isEditing ? 'Edit Jam Kerja' : 'Jam Kerja Saat Ini'}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {isEditing ? (
                            <form onSubmit={submit} className="space-y-6">
                                {/* SUMBER: F-40 -- edit TIDAK menimpa data lama, ini INSERT versi
                                    baru berlaku HARI INI. Boss WAJIB tahu ini bukan "timpa" biasa. */}
                                <p className="rounded-md bg-muted p-3 text-sm text-muted-foreground">
                                    Perubahan berlaku efektif <strong>hari ini</strong>. Pengaturan sebelumnya tetap tersimpan di Log Perubahan di
                                    bawah, tidak hilang.
                                </p>

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
                                        {/* SUMBER: guard "sudah diubah hari ini" (WorkScheduleController::
                                            quickEdit()) dikirim lewat error field ini -- lihat header modul. */}
                                        <InputError message={errors.daily_capacity_minutes} />
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Button disabled={processing}>Simpan Perubahan</Button>
                                    <Button type="button" variant="outline" onClick={cancelEdit}>
                                        Batal
                                    </Button>
                                </div>
                            </form>
                        ) : current ? (
                            <div className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                                    <div>
                                        <div className="text-muted-foreground">Hari kerja</div>
                                        <div className="font-medium">{formatDays(current.days_of_week)}</div>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground">Jam</div>
                                        <div className="font-medium">
                                            {formatTime(current.start_time)}–{formatTime(current.end_time)}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground">Kapasitas</div>
                                        <div className="font-medium">{current.daily_capacity_minutes} menit/hari</div>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground">Berlaku sejak</div>
                                        <div className="font-medium">
                                            {formatDate(current.effective_from)} · diatur oleh {current.creator?.name ?? '-'}
                                        </div>
                                    </div>
                                </div>
                                <Button type="button" size="sm" onClick={startEdit}>
                                    Edit
                                </Button>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                <p className="text-sm text-muted-foreground">Jam kerja organisasi belum diatur.</p>
                                <Button type="button" size="sm" onClick={startEdit}>
                                    Atur Jam Kerja
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <HeadingSmall title="Log Perubahan" description="Riwayat setiap kali jam kerja dibuat/diubah, siapa, dan kapan." />
                    </CardHeader>
                    <CardContent>
                        {logs.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Belum ada perubahan tercatat.</p>
                        ) : (
                            <ul className="divide-y">
                                {logs.map((log) => (
                                    <li key={log.id} className="flex flex-col gap-1 py-3 first:pt-0 last:pb-0">
                                        <div className="flex items-center gap-2 text-sm">
                                            <Badge variant="secondary">{log.event_label}</Badge>
                                            <span className="text-muted-foreground">{formatDate(log.created_at)}</span>
                                        </div>
                                        <p className="text-sm">{log.message}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

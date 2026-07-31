// ==========================================================
// MODUL       : extensions/my-extensions
// KLASIFIKASI : UI
// TUJUAN      : Halaman member/admin — ajukan perpanjangan deadline (F-50) untuk
//               task assigned sendiri + daftar status pengajuan MILIK SENDIRI.
// DIPANGGIL   : DeadlineExtensionController::myExtensions()
// MEMANGGIL   : route('extensions.store'), route('attachments.download') (evidence)
// DATA MASUK  : tasks[] (assigned & belum selesai, dropdown), extensions[] (riwayat
//               requested_by = user login, lintas project)
// DATA KELUAR : POST multipart (evidence opsional) -> DeadlineExtensionController::store()
// RISIKO      : Validasi "tenggat baru harus setelah due_date saat ini" & "task
//               belum selesai" HANYA HINT UI (dropdown sudah difilter belum
//               selesai) — penegakan asli di StoreDeadlineExtensionRequest.
// ==========================================================

import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';

interface TaskOption {
    id: number;
    title: string;
    due_date: string;
    project: { id: number; name: string };
}

interface AttachmentRef {
    id: number;
    file_name: string;
}

interface ExtensionRow {
    id: number;
    task_id: number;
    task: { id: number; title: string; project: { id: number; name: string } };
    requested_due_date: string;
    additional_minutes: number;
    reason: string;
    status: 'pending' | 'approved' | 'rejected';
    review_note: string | null;
    attachments: AttachmentRef[];
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Perpanjangan Saya', href: '/my-extensions' }];

const statusBadge: Record<ExtensionRow['status'], { label: string; className: string }> = {
    pending: { label: 'Menunggu', className: 'bg-amber-500' },
    approved: { label: 'Disetujui', className: 'bg-green-600' },
    rejected: { label: 'Ditolak', className: 'bg-red-600' },
};

export default function MyExtensions({ tasks, extensions }: { tasks: TaskOption[]; extensions: ExtensionRow[] }) {
    const evidenceRef = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, reset } = useForm<{
        task_id: string;
        requested_due_date: string;
        additional_minutes: number;
        reason: string;
        evidence: File | null;
    }>({
        task_id: '',
        requested_due_date: '',
        additional_minutes: 0,
        reason: '',
        evidence: null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('extensions.store'), {
            forceFormData: true,
            onSuccess: () => {
                reset();
                if (evidenceRef.current) evidenceRef.current.value = '';
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Perpanjangan Saya" />

            <div className="flex flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Ajukan Perpanjangan Deadline</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {tasks.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Tidak ada task aktif yang di-assign ke kamu.</p>
                        ) : (
                            <form onSubmit={submit} className="space-y-6">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="task_id">Task</Label>
                                        <Select value={data.task_id} onValueChange={(value) => setData('task_id', value)}>
                                            <SelectTrigger id="task_id">
                                                <SelectValue placeholder="Pilih task" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {tasks.map((t) => (
                                                    <SelectItem key={t.id} value={String(t.id)}>
                                                        {t.project.name} — {t.title}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.task_id} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="requested_due_date">Tenggat baru</Label>
                                        <Input
                                            id="requested_due_date"
                                            type="datetime-local"
                                            value={data.requested_due_date}
                                            onChange={(e) => setData('requested_due_date', e.target.value)}
                                        />
                                        <InputError message={errors.requested_due_date} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="additional_minutes">Tambahan estimasi (menit)</Label>
                                        <Input
                                            id="additional_minutes"
                                            type="number"
                                            min={0}
                                            value={data.additional_minutes}
                                            onChange={(e) => setData('additional_minutes', Number(e.target.value))}
                                        />
                                        <InputError message={errors.additional_minutes} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="evidence">Bukti pendukung (opsional)</Label>
                                        <input
                                            ref={evidenceRef}
                                            id="evidence"
                                            type="file"
                                            className="text-sm"
                                            accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx,.zip"
                                            onChange={(e) => setData('evidence', e.target.files?.[0] ?? null)}
                                        />
                                        <InputError message={errors.evidence} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="reason">Alasan</Label>
                                    <Textarea
                                        id="reason"
                                        rows={3}
                                        value={data.reason}
                                        onChange={(e) => setData('reason', e.target.value)}
                                        placeholder="Jelaskan kenapa butuh perpanjangan..."
                                    />
                                    <InputError message={errors.reason} />
                                </div>

                                <Button disabled={processing}>Ajukan</Button>
                            </form>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Riwayat Pengajuan</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {extensions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Belum ada pengajuan.</p>
                        ) : (
                            extensions.map((ext) => (
                                <div key={ext.id} className="rounded-md border p-3 text-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-medium">
                                            {ext.task.project.name} — {ext.task.title}
                                        </span>
                                        <Badge className={statusBadge[ext.status].className}>{statusBadge[ext.status].label}</Badge>
                                    </div>
                                    <p className="mt-1 text-muted-foreground">
                                        Tenggat diminta: {new Date(ext.requested_due_date).toLocaleString('id-ID')}
                                        {ext.additional_minutes > 0 && ` — +${ext.additional_minutes} menit`}
                                    </p>
                                    <p className="mt-1">{ext.reason}</p>
                                    {ext.review_note && <p className="mt-1 text-muted-foreground">Catatan admin: {ext.review_note}</p>}
                                    {ext.attachments.length > 0 && (
                                        <div className="mt-2 flex flex-col gap-1">
                                            {ext.attachments.map((a) => (
                                                <a
                                                    key={a.id}
                                                    href={route('attachments.download', [ext.task.project.id, ext.task_id, a.id])}
                                                    className="text-xs text-primary hover:underline"
                                                >
                                                    Lihat bukti: {a.file_name}
                                                </a>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

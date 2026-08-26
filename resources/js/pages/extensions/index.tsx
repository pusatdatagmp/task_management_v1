// ==========================================================
// MODUL       : extensions/index
// KLASIFIKASI : UI
// TUJUAN      : Antrean admin — pengajuan perpanjangan deadline PENDING (F-50),
//               approve/reject + lihat evidence.
// DIPANGGIL   : DeadlineExtensionController::index()
// MEMANGGIL   : route('extensions.approve'/'extensions.reject'), route('attachments.download')
// DATA MASUK  : extensions[] (status=pending, dari DeadlineExtensionController::index())
// DATA KELUAR : PATCH approve/reject -> DeadlineExtensionController (F-47 dijalankan
//               DeadlineExtensionObserver, bukan di sini)
// RISIKO      : Tombol di sini HANYA HINT UI — gate ASLI (can:task.approve) di
//               middleware routes/admin.php, pola sama task-status-cell.tsx.
// ==========================================================

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { confirmAction, promptInput } from '@/lib/swal';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

type ContentType = 'file' | 'link' | 'text';

interface AttachmentRef {
    id: number;
    content_type: ContentType;
    file_name: string | null;
    url: string | null;
    body: string | null;
}

interface ExtensionRow {
    id: number;
    task_id: number;
    // F-181 (audit Boss 2026-08-27): NULLABLE -- sama seperti extensions/my-extensions.tsx,
    // task ber-soft-delete (F-16) dikecualikan otomatis dari eager load
    // DeadlineExtensionController::index() (task:id,... TANPA withTrashed()).
    // WAJIB null-check di render (dulu crash blank putih kalau task dihapus).
    task: { id: number; title: string; project: { id: number; name: string } } | null;
    requested_by: { id: number; name: string };
    requested_due_date: string;
    additional_minutes: number;
    reason: string;
    attachments: AttachmentRef[];
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Perpanjangan', href: '/pengaturan/perpanjangan' }];

export default function ExtensionsIndex({ extensions }: { extensions: ExtensionRow[] }) {
    const approve = async (ext: ExtensionRow) => {
        const taskLabel = ext.task ? ext.task.title : 'task yang sudah dihapus';
        if (!(await confirmAction(`Setujui perpanjangan ${ext.task ? `task "${taskLabel}"` : taskLabel} untuk ${ext.requested_by.name}?`))) return;

        router.patch(route('extensions.approve', ext.id), {}, { preserveScroll: true });
    };

    const reject = async (ext: ExtensionRow) => {
        const note = await promptInput('Alasan penolakan (wajib diisi):', {
            title: 'Tolak perpanjangan',
            validator: (value) => (!value.trim() ? 'Alasan wajib diisi.' : null),
        });
        if (!note) return;

        router.patch(route('extensions.reject', ext.id), { review_note: note }, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Perpanjangan Deadline" />

            <div className="flex flex-col gap-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Antrean Perpanjangan ({extensions.length})</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {extensions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Tidak ada pengajuan menunggu keputusan.</p>
                        ) : (
                            extensions.map((ext) => (
                                <div key={ext.id} className="rounded-md border p-3 text-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-medium">
                                            {ext.task ? `${ext.task.project.name} — ${ext.task.title}` : 'Task telah dihapus'}
                                        </span>
                                        <span className="text-xs text-muted-foreground">Diajukan oleh {ext.requested_by.name}</span>
                                    </div>
                                    <p className="mt-1 text-muted-foreground">
                                        Tenggat diminta: {new Date(ext.requested_due_date).toLocaleString('id-ID')}
                                        {ext.additional_minutes > 0 && ` — +${ext.additional_minutes} menit`}
                                    </p>
                                    <p className="mt-1">{ext.reason}</p>
                                    {ext.attachments.length > 0 && (
                                        <div className="mt-2 flex flex-col gap-1">
                                            {ext.attachments.map((a) => (
                                                <div key={a.id} className="text-xs">
                                                    {/* F-181: link download BUTUH project.id -- kalau task sudah
                                                        dihapus, tampil sebagai label statis alih-alih crash. */}
                                                    {a.content_type === 'file' && ext.task && (
                                                        <a
                                                            href={route('attachments.download', [ext.task.project.id, ext.task_id, a.id])}
                                                            className="text-primary hover:underline"
                                                        >
                                                            Lihat bukti: {a.file_name}
                                                        </a>
                                                    )}
                                                    {a.content_type === 'file' && !ext.task && (
                                                        <span className="text-muted-foreground">Bukti: {a.file_name} (task terhapus)</span>
                                                    )}
                                                    {a.content_type === 'link' && (
                                                        <a href={a.url ?? '#'} target="_blank" rel="noopener noreferrer" className="text-primary hover:underline">
                                                            🔗 Lihat bukti: {a.url}
                                                        </a>
                                                    )}
                                                    {a.content_type === 'text' && (
                                                        <p className="whitespace-pre-wrap break-words text-muted-foreground">Bukti: {a.body}</p>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    <div className="mt-3 flex gap-2">
                                        <Button type="button" size="sm" onClick={() => approve(ext)}>
                                            Approve
                                        </Button>
                                        <Button type="button" size="sm" variant="outline" onClick={() => reject(ext)}>
                                            Reject
                                        </Button>
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

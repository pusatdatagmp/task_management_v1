// ==========================================================
// MODUL       : task-attachments
// KLASIFIKASI : UI
// TUJUAN      : Upload/list/download/hapus attachment output task (F-49) — dipakai
//               tasks/show.tsx. Evidence (extension) DITUNDA ke H6.
// DIPANGGIL   : tasks/show.tsx
// MEMANGGIL   : route('attachments.store'/'attachments.download'/'attachments.destroy')
// DATA MASUK  : projectId/taskId, attachments[] (dari TaskController::show()),
//               canManageTask (F-90 task.manage, admin), isAssignee, isLocked
//               (F-104 — approved_at task sudah terisi)
// DATA KELUAR : POST upload (FormData, F-49), GET download (navigasi browser biasa,
//               server yang cek permission — F-95), DELETE hapus (F-105, admin only)
// RISIKO      : SUMBER : upload/hapus tombol di sini HANYA HINT UI — validasi ASLI
//               (assignee/admin F-95, freeze F-104, admin-only F-105) tetap di
//               AttachmentController. Kalau kondisi di sini salah, paling buruk
//               tombol nongol/hilang keliru, BUKAN lubang keamanan (pola sama
//               task-status-cell.tsx).
// ==========================================================

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';

interface AttachmentData {
    id: number;
    file_name: string;
    file_size: number;
    uploaded_by: { id: number; name: string };
    created_at: string;
}

interface TaskAttachmentsProps {
    projectId: number;
    taskId: number;
    attachments: AttachmentData[];
    canManageTask: boolean;
    isAssignee: boolean;
    isLocked: boolean;
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function TaskAttachments({ projectId, taskId, attachments, canManageTask, isAssignee, isLocked }: TaskAttachmentsProps) {
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const canUpload = (canManageTask || isAssignee) && !isLocked;

    const upload = () => {
        const file = inputRef.current?.files?.[0];
        if (!file) return;

        setUploading(true);
        setError(null);

        router.post(
            route('attachments.store', [projectId, taskId]),
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (errors) => setError(errors.file ?? 'Upload gagal.'),
                onFinish: () => {
                    setUploading(false);
                    if (inputRef.current) inputRef.current.value = '';
                },
            },
        );
    };

    const destroy = (attachment: AttachmentData) => {
        if (!confirm(`Hapus lampiran "${attachment.file_name}"?`)) return;
        router.delete(route('attachments.destroy', [projectId, taskId, attachment.id]), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Lampiran Output</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {attachments.length === 0 && <p className="text-sm text-muted-foreground">Belum ada lampiran.</p>}

                {attachments.map((a) => (
                    <div key={a.id} className="flex items-center justify-between gap-2 rounded-md border p-2 text-sm">
                        <div className="flex min-w-0 flex-col">
                            <a
                                href={route('attachments.download', [projectId, taskId, a.id])}
                                className="truncate font-medium hover:underline"
                            >
                                {a.file_name}
                            </a>
                            <span className="text-xs text-muted-foreground">
                                {formatBytes(a.file_size)} — {a.uploaded_by.name} — {new Date(a.created_at).toLocaleString('id-ID')}
                            </span>
                        </div>
                        {canManageTask && (
                            <Button type="button" variant="outline" size="sm" onClick={() => destroy(a)}>
                                Hapus
                            </Button>
                        )}
                    </div>
                ))}

                {canUpload && (
                    <div className="flex flex-col gap-2 border-t pt-3">
                        <input ref={inputRef} type="file" className="text-sm" accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx,.zip" />
                        <Button type="button" size="sm" onClick={upload} disabled={uploading} className="self-start">
                            {uploading ? 'Mengunggah...' : 'Upload'}
                        </Button>
                        {error && <p className="text-xs text-destructive">{error}</p>}
                    </div>
                )}

                {/* F-104: begitu task disetujui, output beku — sejalan actual_minutes (F-39). */}
                {isLocked && (
                    <p className="text-xs text-muted-foreground">Terkunci — task sudah disetujui, lampiran output tidak bisa ditambah lagi.</p>
                )}
            </CardContent>
        </Card>
    );
}

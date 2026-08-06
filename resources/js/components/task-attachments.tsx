// ==========================================================
// MODUL       : task-attachments
// KLASIFIKASI : UI
// TUJUAN      : Upload/list/download/hapus attachment output task (F-49) — dipakai
//               tasks/show.tsx. Evidence (extension) DITUNDA ke H6.
//               Revisi 2026-08-06 item 4: 3 mode ISI (content_type) — Upload File
//               (perilaku lama), Sematkan Link (URL eksternal), Tulis Teks (teks
//               langsung tanpa file terpisah).
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
//               task-status-cell.tsx). Link dirender `target="_blank" rel="noopener
//               noreferrer"` (bukan `dangerouslySetInnerHTML`) -- teks (body) DIRENDER
//               APA ADANYA sebagai text node React (auto-escape XSS), bukan HTML.
// ==========================================================

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { confirmAction } from '@/lib/swal';
import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';

type ContentType = 'file' | 'link' | 'text';

interface AttachmentData {
    id: number;
    content_type: ContentType;
    file_name: string | null;
    file_size: number | null;
    url: string | null;
    body: string | null;
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
    const [mode, setMode] = useState<ContentType>('file');
    const [linkValue, setLinkValue] = useState('');
    const [textValue, setTextValue] = useState('');
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const canUpload = (canManageTask || isAssignee) && !isLocked;

    const submitAttachment = () => {
        setError(null);

        const payload: Record<string, string | File> = { content_type: mode };
        if (mode === 'file') {
            const file = inputRef.current?.files?.[0];
            if (!file) return;
            payload.file = file;
        } else if (mode === 'link') {
            if (!linkValue.trim()) return;
            payload.url = linkValue.trim();
        } else {
            if (!textValue.trim()) return;
            payload.body = textValue.trim();
        }

        setUploading(true);

        router.post(route('attachments.store', [projectId, taskId]), payload, {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => setError(errors.file ?? errors.url ?? errors.body ?? 'Gagal menyimpan lampiran.'),
            onSuccess: () => {
                setLinkValue('');
                setTextValue('');
                if (inputRef.current) inputRef.current.value = '';
            },
            onFinish: () => setUploading(false),
        });
    };

    const destroy = async (attachment: AttachmentData) => {
        const label = attachment.content_type === 'file' ? attachment.file_name : attachment.content_type === 'link' ? attachment.url : 'teks ini';
        if (!(await confirmAction(`Hapus lampiran "${label}"?`, { danger: true }))) return;
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
                    <div key={a.id} className="flex items-start justify-between gap-2 rounded-md border p-2 text-sm">
                        <div className="flex min-w-0 flex-col gap-1">
                            {a.content_type === 'file' && (
                                <a
                                    href={route('attachments.download', [projectId, taskId, a.id])}
                                    className="truncate font-medium hover:underline"
                                >
                                    {a.file_name}
                                </a>
                            )}
                            {a.content_type === 'link' && (
                                <a href={a.url ?? '#'} target="_blank" rel="noopener noreferrer" className="truncate font-medium hover:underline">
                                    🔗 {a.url}
                                </a>
                            )}
                            {a.content_type === 'text' && <p className="whitespace-pre-wrap break-words">{a.body}</p>}
                            <span className="text-xs text-muted-foreground">
                                {a.content_type === 'file' && a.file_size !== null ? `${formatBytes(a.file_size)} — ` : ''}
                                {a.uploaded_by.name} — {new Date(a.created_at).toLocaleString('id-ID')}
                            </span>
                        </div>
                        {canManageTask && (
                            <Button type="button" variant="outline" size="sm" onClick={() => destroy(a)} className="shrink-0">
                                Hapus
                            </Button>
                        )}
                    </div>
                ))}

                {canUpload && (
                    <div className="flex flex-col gap-2 border-t pt-3">
                        <div className="flex gap-1">
                            {(['file', 'link', 'text'] as ContentType[]).map((m) => (
                                <Button key={m} type="button" size="sm" variant={mode === m ? 'default' : 'outline'} onClick={() => setMode(m)}>
                                    {m === 'file' ? 'Upload File' : m === 'link' ? 'Sematkan Link' : 'Tulis Teks'}
                                </Button>
                            ))}
                        </div>

                        {mode === 'file' && <input ref={inputRef} type="file" className="text-sm" accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx,.zip" />}
                        {mode === 'link' && (
                            <input
                                type="url"
                                placeholder="https://..."
                                value={linkValue}
                                onChange={(e) => setLinkValue(e.target.value)}
                                className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            />
                        )}
                        {mode === 'text' && (
                            <Textarea placeholder="Tulis teks lampiran..." value={textValue} onChange={(e) => setTextValue(e.target.value)} rows={4} />
                        )}

                        <Button type="button" size="sm" onClick={submitAttachment} disabled={uploading} className="self-start">
                            {uploading ? 'Menyimpan...' : 'Simpan'}
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

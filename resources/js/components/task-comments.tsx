// ==========================================================
// MODUL       : task-comments
// KLASIFIKASI : UI
// TUJUAN      : Diskusi per task (v1.0 H3, F-113/F-114/F-115) — daftar komentar +
//               form tulis dengan @mention autocomplete. F-113: komentar TIDAK
//               PERNAH masuk activity-log-UI (itu H4), ini murni tabel comments-nya.
// DIPANGGIL   : tasks/show.tsx
// MEMANGGIL   : route('comments.store'/'update'/'destroy')
// DATA MASUK  : comments[] (dari TaskController::show(), termasuk yang soft-deleted
//               sebagai placeholder — is_deleted), projectMembers[] (whitelist mention)
// DATA KELUAR : POST/PUT/DELETE -> CommentController
// RISIKO      : Token mention DISIMPAN sebagai `@[Nama](id)` mentah di body (bukan
//               format tampil) — renderBody() SATU-SATUNYA tempat yang menerjemahkan
//               token itu jadi span "@Nama" ter-highlight. Kalau ada tempat lain
//               yang menampilkan body mentah, user akan melihat markup aneh alih-alih
//               nama — selalu lewat renderBody(), jangan render {comment.body} langsung.
// ==========================================================

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { useInitials } from '@/hooks/use-initials';
import { confirmAction } from '@/lib/swal';
import { router } from '@inertiajs/react';
import { Fragment, useRef, useState } from 'react';

interface UserOption {
    id: number;
    name: string;
}

interface CommentData {
    id: number;
    body: string | null;
    user: UserOption;
    created_at: string;
    is_edited: boolean;
    is_deleted: boolean;
    is_mine: boolean;
}

interface TaskCommentsProps {
    projectId: number;
    taskId: number;
    comments: CommentData[];
    projectMembers: UserOption[];
}

// SUMBER: token mention mentah "@[Nama](id)" -> ditulis CommentController saat
// simpan (extractMentionedUserIds membaca pola YANG SAMA). Di sini cuma dipakai
// untuk TAMPILAN (highlight), bukan sumber kebenaran siapa disebut.
const MENTION_PATTERN = /@\[([^\]]+)\]\(\d+\)/g;

function renderBody(body: string) {
    const parts: React.ReactNode[] = [];
    let lastIndex = 0;
    let match: RegExpExecArray | null;
    let key = 0;

    MENTION_PATTERN.lastIndex = 0;
    while ((match = MENTION_PATTERN.exec(body)) !== null) {
        if (match.index > lastIndex) {
            parts.push(<Fragment key={key++}>{body.slice(lastIndex, match.index)}</Fragment>);
        }
        parts.push(
            <span key={key++} className="font-medium text-primary">
                @{match[1]}
            </span>,
        );
        lastIndex = match.index + match[0].length;
    }
    if (lastIndex < body.length) {
        parts.push(<Fragment key={key++}>{body.slice(lastIndex)}</Fragment>);
    }

    return parts;
}

function timeAgo(isoDate: string): string {
    const diffMs = Date.now() - new Date(isoDate).getTime();
    const minutes = Math.floor(diffMs / 60000);

    if (minutes < 1) return 'baru saja';
    if (minutes < 60) return `${minutes} menit lalu`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} jam lalu`;
    const days = Math.floor(hours / 24);

    return `${days} hari lalu`;
}

/**
 * KONTRAK: textarea terkontrol + deteksi "@ketikan" tepat sebelum kursor (regex
 * di akhir substring sebelum cursor) -> tampilkan dropdown member yang cocok.
 * Pilih member -> ganti "@ketikan" dengan token "@[Nama](id) " utuh. Dipakai
 * BERSAMA oleh form tulis baru dan form edit (props onSubmit beda, logic sama).
 */
function MentionTextarea({
    value,
    onChange,
    members,
    placeholder,
    autoFocus,
}: {
    value: string;
    onChange: (value: string) => void;
    members: UserOption[];
    placeholder?: string;
    autoFocus?: boolean;
}) {
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const [suggestions, setSuggestions] = useState<UserOption[]>([]);

    function handleChange(e: React.ChangeEvent<HTMLTextAreaElement>) {
        const text = e.target.value;
        onChange(text);

        const cursor = e.target.selectionStart;
        const beforeCursor = text.slice(0, cursor);
        const match = beforeCursor.match(/@([a-zA-Z0-9 ]{0,30})$/);

        if (!match) {
            setSuggestions([]);
            return;
        }

        const query = match[1].trim().toLowerCase();
        setSuggestions(query.length === 0 ? members.slice(0, 5) : members.filter((m) => m.name.toLowerCase().includes(query)).slice(0, 5));
    }

    function selectMention(member: UserOption) {
        const textarea = textareaRef.current;
        if (!textarea) return;

        const cursor = textarea.selectionStart;
        const beforeCursor = value.slice(0, cursor);
        const match = beforeCursor.match(/@([a-zA-Z0-9 ]{0,30})$/);
        if (!match) return;

        const start = cursor - match[0].length;
        const token = `@[${member.name}](${member.id}) `;
        const newValue = value.slice(0, start) + token + value.slice(cursor);

        onChange(newValue);
        setSuggestions([]);

        // SUMBER: fokus balik + posisikan kursor SETELAH token yang baru
        // disisipkan, supaya user bisa lanjut mengetik tanpa klik ulang.
        requestAnimationFrame(() => {
            textarea.focus();
            const newCursor = start + token.length;
            textarea.setSelectionRange(newCursor, newCursor);
        });
    }

    return (
        <div className="relative">
            <Textarea
                ref={textareaRef}
                value={value}
                onChange={handleChange}
                placeholder={placeholder ?? 'Tulis komentar... ketik @ untuk menyebut member'}
                rows={3}
                autoFocus={autoFocus}
            />
            {suggestions.length > 0 && (
                <div className="absolute z-10 mt-1 w-full max-w-xs rounded-md border bg-popover shadow-md">
                    {suggestions.map((m) => (
                        <button
                            key={m.id}
                            type="button"
                            className="block w-full px-3 py-1.5 text-left text-sm hover:bg-accent"
                            onClick={() => selectMention(m)}
                        >
                            {m.name}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function TaskComments({ projectId, taskId, comments, projectMembers }: TaskCommentsProps) {
    const getInitials = useInitials();
    const [newBody, setNewBody] = useState('');
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editBody, setEditBody] = useState('');

    const submitNew = () => {
        if (!newBody.trim()) return;

        router.post(
            route('comments.store', [projectId, taskId]),
            { body: newBody },
            { preserveScroll: true, onSuccess: () => setNewBody('') },
        );
    };

    const startEdit = (comment: CommentData) => {
        setEditingId(comment.id);
        setEditBody(comment.body ?? '');
    };

    const submitEdit = () => {
        if (!editBody.trim() || editingId === null) return;

        router.put(
            route('comments.update', [projectId, taskId, editingId]),
            { body: editBody },
            { preserveScroll: true, onSuccess: () => setEditingId(null) },
        );
    };

    const destroy = async (comment: CommentData) => {
        if (!(await confirmAction('Hapus komentar ini?', { danger: true }))) return;
        router.delete(route('comments.destroy', [projectId, taskId, comment.id]), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Komentar ({comments.filter((c) => !c.is_deleted).length})</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {comments.length === 0 && <p className="text-sm text-muted-foreground">Belum ada komentar.</p>}

                {comments.map((comment) => (
                    <div key={comment.id} className="flex gap-3">
                        <Avatar className="h-8 w-8 shrink-0">
                            <AvatarFallback className="bg-neutral-200 text-xs text-black dark:bg-neutral-700 dark:text-white">
                                {getInitials(comment.user.name)}
                            </AvatarFallback>
                        </Avatar>

                        <div className="flex-1">
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <span className="font-medium">{comment.user.name}</span>
                                <span className="text-xs text-muted-foreground">{timeAgo(comment.created_at)}</span>
                                {comment.is_edited && <span className="text-xs text-muted-foreground">(diedit)</span>}
                            </div>

                            {comment.is_deleted ? (
                                <p className="text-sm italic text-muted-foreground">[Komentar dihapus]</p>
                            ) : editingId === comment.id ? (
                                <div className="mt-1 flex flex-col gap-2">
                                    <MentionTextarea value={editBody} onChange={setEditBody} members={projectMembers} autoFocus />
                                    <div className="flex gap-2">
                                        <Button type="button" size="sm" onClick={submitEdit}>
                                            Simpan
                                        </Button>
                                        <Button type="button" size="sm" variant="outline" onClick={() => setEditingId(null)}>
                                            Batal
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <>
                                    <p className="text-sm whitespace-pre-wrap">{renderBody(comment.body ?? '')}</p>
                                    {comment.is_mine && (
                                        <div className="mt-1 flex gap-3 text-xs text-muted-foreground">
                                            <button type="button" className="hover:underline" onClick={() => startEdit(comment)}>
                                                Edit
                                            </button>
                                            <button type="button" className="hover:underline" onClick={() => destroy(comment)}>
                                                Hapus
                                            </button>
                                        </div>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                ))}

                <div className="flex flex-col gap-2 border-t pt-4">
                    <MentionTextarea value={newBody} onChange={setNewBody} members={projectMembers} />
                    <Button type="button" size="sm" onClick={submitNew} className="self-start">
                        Kirim
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

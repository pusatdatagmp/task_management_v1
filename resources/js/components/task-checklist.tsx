// ==========================================================
// MODUL       : task-checklist
// KLASIFIKASI : UI
// TUJUAN      : Kelola checklist dalam-tugas (F-123) — dipakai tasks/show.tsx.
//               Gate transisi ->review (F-127) ditegakkan SERVER-side di
//               TaskTransitionService, komponen ini MURNI CRUD + tampilan progress,
//               nol validasi gate di sini (server yang menolak submit kalau bolong).
//               Revisi 2026-08-06 (Boss): (1) tambah item SEKARANG task.manage
//               SATU-SATUNYA — assignee tidak lagi bisa tambah item baru (dulu
//               boleh, dicabut). (2) centang (toggle) HANYA aktif saat
//               isWorkState=true (task sudah "Mulai", F-44/H7/F-132/F-138) —
//               checkbox disabled + hint teks saat task masih TODO/Review/Selesai.
// DIPANGGIL   : tasks/show.tsx
// MEMANGGIL   : route('checklist-items.store'/'update'/'toggle'/'destroy')
// DATA MASUK  : projectId/taskId, items[] (dari TaskController::show()),
//               canManageTask (F-90 task.manage — tambah/ubah teks/hapus),
//               isWorkState (task.task_status.is_work_state — gate toggle)
// DATA KELUAR : POST/PUT/PATCH/DELETE -> TaskChecklistItemController
// RISIKO      : SUMBER : tombol tambah/edit/hapus/centang di sini HANYA HINT UI
//               (pola sama task-attachments.tsx) — validasi ASLI (task.manage
//               only utk tambah, is_work_state utk toggle) tetap di
//               TaskChecklistItemController. Progress (done/total) MURNI dari
//               items[] yang sama yang dipakai gate server, nol hitung ganda.
// ==========================================================

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { confirmAction } from '@/lib/swal';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface ChecklistItemData {
    id: number;
    text: string;
    is_done: boolean;
}

interface TaskChecklistProps {
    projectId: number;
    taskId: number;
    items: ChecklistItemData[];
    canManageTask: boolean;
    isAssignee: boolean;
    isWorkState: boolean;
}

export default function TaskChecklist({ projectId, taskId, items, canManageTask, isAssignee, isWorkState }: TaskChecklistProps) {
    const [newText, setNewText] = useState('');
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editText, setEditText] = useState('');

    // Revisi 2026-08-06: tambah item = task.manage ONLY (assignee dicabut).
    const canAdd = canManageTask;
    // Centang cuma boleh kalau task SEDANG dikerjakan (F-44 flag, bukan nama status).
    const canToggle = (canManageTask || isAssignee) && isWorkState;
    const doneCount = items.filter((i) => i.is_done).length;

    const addItem = () => {
        if (!newText.trim()) return;

        router.post(
            route('checklist-items.store', [projectId, taskId]),
            { text: newText },
            { preserveScroll: true, onSuccess: () => setNewText('') },
        );
    };

    const toggle = (item: ChecklistItemData) => {
        router.patch(route('checklist-items.toggle', [projectId, taskId, item.id]), {}, { preserveScroll: true });
    };

    const startEdit = (item: ChecklistItemData) => {
        setEditingId(item.id);
        setEditText(item.text);
    };

    const submitEdit = () => {
        if (!editText.trim() || editingId === null) return;

        router.put(
            route('checklist-items.update', [projectId, taskId, editingId]),
            { text: editText },
            { preserveScroll: true, onSuccess: () => setEditingId(null) },
        );
    };

    const destroy = async (item: ChecklistItemData) => {
        if (!(await confirmAction(`Hapus item checklist "${item.text}"?`, { danger: true }))) return;
        router.delete(route('checklist-items.destroy', [projectId, taskId, item.id]), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    Checklist {items.length > 0 && `(${doneCount}/${items.length})`}
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-2">
                {items.length === 0 && <p className="text-sm text-muted-foreground">Belum ada item checklist.</p>}

                {items.length > 0 && !isWorkState && (
                    <p className="text-xs text-muted-foreground">Task belum dimulai — centang checklist aktif setelah task "Mulai" dikerjakan.</p>
                )}

                {items.map((item) => (
                    <div key={item.id} className="flex items-center gap-2 text-sm">
                        <Checkbox checked={item.is_done} onCheckedChange={() => toggle(item)} disabled={!canToggle} />

                        {editingId === item.id ? (
                            <div className="flex flex-1 items-center gap-2">
                                <Input value={editText} onChange={(e) => setEditText(e.target.value)} autoFocus />
                                <Button type="button" size="sm" onClick={submitEdit}>
                                    Simpan
                                </Button>
                                <Button type="button" size="sm" variant="outline" onClick={() => setEditingId(null)}>
                                    Batal
                                </Button>
                            </div>
                        ) : (
                            <>
                                <span className={`flex-1 ${item.is_done ? 'text-muted-foreground line-through' : ''}`}>{item.text}</span>
                                {canManageTask && (
                                    <div className="flex gap-2 text-xs text-muted-foreground">
                                        <button type="button" className="hover:underline" onClick={() => startEdit(item)}>
                                            Edit
                                        </button>
                                        <button type="button" className="hover:underline" onClick={() => destroy(item)}>
                                            Hapus
                                        </button>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                ))}

                {canAdd && (
                    <div className="flex items-center gap-2 border-t pt-3">
                        <Input
                            placeholder="Tambah item checklist..."
                            value={newText}
                            onChange={(e) => setNewText(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    addItem();
                                }
                            }}
                        />
                        <Button type="button" size="sm" onClick={addItem} className="shrink-0">
                            Tambah
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

// ==========================================================
// MODUL       : tasks/board
// KLASIFIKASI : UI
// TUJUAN      : Papan Kanban (v1.0 H1 render + H2 drag, F-109/F-110/F-111) —
//               tampilan alternatif List View, task dikelompokkan per kolom status,
//               kartu bisa diseret antar kolom.
// DIPANGGIL   : BoardController::index()
// MEMANGGIL   : TaskLiveCounter (REUSE F-94, bukan komponen counter baru),
//               route('tasks.show') untuk buka detail (REUSE halaman yang ada),
//               route('tasks.status') untuk drop (REUSE endpoint dropdown lama, F-111)
// DATA MASUK  : project, columns[] (status urut position, tiap kolom bawa cards[]
//               dengan can_drag dari server, F-95), members[], filters
// DATA KELUAR : router.get (filter server-side), router.patch (drop -> status change,
//               endpoint SAMA dengan TaskStatusCell), navigasi ke tasks.show
// RISIKO      : SUMBER F-110 — validColumnIds dihitung SEKALI saat onDragStart dari
//               posisi kolom asal (isValidDropTarget, lib/board-column-validity.ts),
//               dipakai untuk redup + useDroppable({disabled}) SEBELUM user melepas.
//               Ini HANYA HINT UI — server (TaskTransitionService) tetap validasi F-45
//               penuh saat drop beneran terjadi (C1), client TIDAK PERNAH dipercaya
//               sendirian. F-33 — drop SAH memindah kartu ke kolom baru INSTAN
//               (state lokal) sebelum server konfirmasi; gagal -> revert ke snapshot
//               sebelum drag + banner error, sukses -> prop refresh alami dari
//               Inertia redirect-back menggantikan state optimistic (konsisten,
//               tidak pernah konflik). due_status/live_counter SEPENUHNYA dari
//               props server (F-109/F-94), komponen ini tidak menghitung ulang.
// ==========================================================

import TaskLiveCounter, { type LiveCounterData } from '@/components/task-live-counter';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { isValidDropTarget } from '@/lib/board-column-validity';
import { PRIORITY_QUADRANT_COLOR, PRIORITY_QUADRANT_LABEL, type PriorityQuadrant } from '@/lib/priority-quadrant';
import { type BreadcrumbItem } from '@/types';
import {
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
    type DragEndEvent,
    type DragStartEvent,
} from '@dnd-kit/core';
import { sortableKeyboardCoordinates } from '@dnd-kit/sortable';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface UserOption {
    id: number;
    name: string;
}

interface CardData {
    id: number;
    title: string;
    task_type: string;
    priority: string;
    priority_quadrant: PriorityQuadrant | null;
    points: number;
    due_date: string;
    task_status_id: number;
    is_work_state: boolean;
    assignees: UserOption[];
    children_count: number;
    live_counter: LiveCounterData | null;
    due_status: 'overdue' | 'today' | null;
    can_drag: boolean;
}

interface ColumnData {
    id: number;
    name: string;
    color: string;
    position: number;
    is_work_state: boolean;
    is_review: boolean;
    is_completed: boolean;
    cards: CardData[];
}

interface Filters {
    assignee: number[];
    priority: string[];
}

interface BoardProps {
    project: { id: number; name: string };
    columns: ColumnData[];
    members: UserOption[];
    filters: Filters;
}

const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

function AssigneeStack({ assignees }: { assignees: UserOption[] }) {
    const getInitials = useInitials();

    if (assignees.length === 0) {
        return null;
    }

    return (
        <div className="flex -space-x-2" title={assignees.map((a) => a.name).join(', ')}>
            {assignees.slice(0, 3).map((a) => (
                <Avatar key={a.id} className="h-6 w-6 border-2 border-background">
                    <AvatarFallback className="bg-neutral-200 text-[10px] text-black dark:bg-neutral-700 dark:text-white">
                        {getInitials(a.name)}
                    </AvatarFallback>
                </Avatar>
            ))}
            {assignees.length > 3 && (
                <Avatar className="h-6 w-6 border-2 border-background">
                    <AvatarFallback className="bg-neutral-200 text-[10px] text-black dark:bg-neutral-700 dark:text-white">
                        +{assignees.length - 3}
                    </AvatarFallback>
                </Avatar>
            )}
        </div>
    );
}

function TaskCardContent({ card }: { card: CardData }) {
    return (
        <>
            <div className="flex items-start justify-between gap-2">
                <span className="font-medium">{card.title}</span>
                <Badge variant="outline" className="shrink-0 text-[10px] capitalize">
                    {card.task_type}
                </Badge>
            </div>

            <TaskLiveCounter isWorkState={card.is_work_state} liveCounter={card.live_counter} variant="dot" />

            <div className="flex flex-wrap items-center gap-1.5">
                {/* F-122/F-126: badge Eisenhower gantikan tampilan enum priority lama. */}
                {card.priority_quadrant && (
                    <Badge
                        className="border-transparent text-[10px] text-white"
                        style={{ backgroundColor: PRIORITY_QUADRANT_COLOR[card.priority_quadrant] }}
                    >
                        {PRIORITY_QUADRANT_LABEL[card.priority_quadrant]}
                    </Badge>
                )}
                <Badge variant="outline" className="text-[10px]">
                    {card.points} poin
                </Badge>
                {card.due_status === 'overdue' && (
                    <Badge className="border-transparent bg-red-600 text-[10px] text-white hover:bg-red-600">Terlambat</Badge>
                )}
                {card.due_status === 'today' && (
                    <Badge className="border-transparent bg-amber-500 text-[10px] text-white hover:bg-amber-500">Hari ini</Badge>
                )}
                {card.children_count > 0 && <span className="text-[10px] text-muted-foreground">{card.children_count} subtugas</span>}
            </div>

            <div className="flex items-center justify-between gap-2">
                <span className="text-xs text-muted-foreground">{new Date(card.due_date).toLocaleDateString('id-ID')}</span>
                <AssigneeStack assignees={card.assignees} />
            </div>
        </>
    );
}

/**
 * KONTRAK: C4/F-95 — draggable HANYA kalau card.can_drag (assignee ATAU
 * task.manage, dihitung server). Kartu yang tidak boleh diseret tetap bisa
 * diklik untuk buka detail seperti biasa, cuma tidak merespons drag gesture.
 */
function DraggableTaskCard({ projectId, card }: { projectId: number; card: CardData }) {
    const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
        id: card.id,
        disabled: !card.can_drag,
        data: { card },
    });

    const style = transform
        ? { transform: `translate3d(${transform.x}px, ${transform.y}px, 0)`, zIndex: 10 }
        : undefined;

    return (
        <Link
            ref={setNodeRef}
            href={route('tasks.show', [projectId, card.id])}
            style={style}
            className={`flex flex-col gap-2 rounded-md border bg-card p-3 text-sm shadow-sm hover:bg-accent ${
                isDragging ? 'opacity-30' : ''
            } ${card.can_drag ? 'cursor-grab active:cursor-grabbing' : ''}`}
            {...listeners}
            {...attributes}
        >
            <TaskCardContent card={card} />
        </Link>
    );
}

/**
 * KONTRAK: F-110 — kolom di-disable (useDroppable({disabled})) saat kolom ini
 * TAK-SAH untuk kartu yang sedang diseret. Disabled = dnd-kit sendiri menolak
 * collision, jadi drop paksa (mis. keyboard) di sini otomatis no-op (B3) —
 * bukan cuma opacity kosmetik.
 */
function DroppableColumn({ column, isDragging, isValidTarget, children }: { column: ColumnData; isDragging: boolean; isValidTarget: boolean; children: React.ReactNode }) {
    const { setNodeRef, isOver } = useDroppable({ id: column.id, disabled: isDragging && !isValidTarget });

    const dimmed = isDragging && !isValidTarget;

    return (
        <div
            ref={setNodeRef}
            className={`flex w-72 shrink-0 flex-col gap-3 rounded-lg border p-3 transition-opacity ${
                dimmed ? 'opacity-30' : 'opacity-100'
            } ${isOver && isValidTarget ? 'border-primary bg-primary/5' : 'bg-muted/30'}`}
        >
            <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: column.color }} />
                    <span className="text-sm font-semibold">{column.name}</span>
                </div>
                <Badge variant="outline">{column.cards.length}</Badge>
            </div>

            <div className="flex flex-col gap-2">{children}</div>
        </div>
    );
}

export default function TaskBoard({ project, columns: initialColumns, members, filters }: BoardProps) {
    const [columns, setColumns] = useState(initialColumns);
    const [activeCard, setActiveCard] = useState<CardData | null>(null);
    const [sourcePosition, setSourcePosition] = useState<number | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    // SUMBER: props berubah tiap kali Inertia visit selesai (filter, atau redirect-
    // back setelah drop) — sinkronkan state lokal ke sumber kebenaran server.
    useEffect(() => {
        setColumns(initialColumns);
    }, [initialColumns]);

    useEffect(() => {
        if (!errorMessage) return;
        const timer = setTimeout(() => setErrorMessage(null), 5000);
        return () => clearTimeout(timer);
    }, [errorMessage]);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Project', href: '/projects' },
        { title: project.name, href: route('projects.edit', project.id) },
        { title: 'Board', href: '#' },
    ];

    const applyFilters = (overrides: Partial<Filters>) => {
        router.get(route('tasks.board', project.id), { ...filters, ...overrides }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const toggleAssignee = (id: number) => {
        applyFilters({ assignee: filters.assignee.includes(id) ? filters.assignee.filter((v) => v !== id) : [...filters.assignee, id] });
    };

    const togglePriority = (value: string) => {
        applyFilters({ priority: filters.priority.includes(value) ? filters.priority.filter((v) => v !== value) : [...filters.priority, value] });
    };

    const hasActiveFilter = filters.assignee.length > 0 || filters.priority.length > 0;

    function handleDragStart(event: DragStartEvent) {
        const card = event.active.data.current?.card as CardData | undefined;
        if (!card) return;

        const origin = columns.find((c) => c.id === card.task_status_id);
        setActiveCard(card);
        setSourcePosition(origin?.position ?? null);
    }

    function handleDragEnd(event: DragEndEvent) {
        const card = activeCard;
        setActiveCard(null);
        setSourcePosition(null);

        if (!card || !event.over) return;

        const targetColumnId = Number(event.over.id);
        const targetColumn = columns.find((c) => c.id === targetColumnId);
        const origin = columns.find((c) => c.id === card.task_status_id);

        if (!targetColumn || !origin || targetColumn.id === origin.id) return;

        // B3: pertahanan kedua — walau useDroppable sudah disabled untuk kolom
        // tak-sah, cek ulang di sini (aturan C, F-110) sebelum optimistic move.
        if (!isValidDropTarget(origin.position, targetColumn.position)) return;

        const snapshot = columns;

        // F-33: pindah INSTAN di layar dulu, server dikonfirmasi belakangan.
        setColumns((prev) =>
            prev.map((c) => {
                if (c.id === origin.id) return { ...c, cards: c.cards.filter((cd) => cd.id !== card.id) };
                if (c.id === targetColumn.id) return { ...c, cards: [...c.cards, { ...card, task_status_id: targetColumn.id }] };
                return c;
            }),
        );

        // F-111: endpoint SAMA dengan TaskStatusCell (dropdown) — service+observer
        // yang menegakkan F-45/F-41/F-51, drag tidak bikin jalur data baru.
        router.patch(
            route('tasks.status', [project.id, card.id]),
            { task_status_id: targetColumn.id },
            {
                preserveScroll: true,
                onError: (errors) => {
                    // F-33: gagal -> revert ke snapshot sebelum drag, bukan biarkan nyangkut.
                    setColumns(snapshot);
                    setErrorMessage(errors.task_status_id ?? 'Gagal memindahkan task. Coba lagi.');
                },
            },
        );
    }

    const validColumnIds = new Set(
        sourcePosition === null ? [] : columns.filter((c) => isValidDropTarget(sourcePosition, c.position)).map((c) => c.id),
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Board — ${project.name}`} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Board — {project.name}</h1>
                    {/* B3: toggle List <-> Board untuk project yang sama. */}
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('tasks.index', project.id)}>List View</Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-start gap-6 rounded-lg border p-4 text-sm">
                    <div className="flex flex-col gap-1">
                        <span className="font-medium">Assignee</span>
                        {members.map((m) => (
                            <label key={m.id} className="flex items-center gap-2">
                                <input type="checkbox" checked={filters.assignee.includes(m.id)} onChange={() => toggleAssignee(m.id)} />
                                {m.name}
                            </label>
                        ))}
                        {members.length === 0 && <span className="text-muted-foreground">-</span>}
                    </div>

                    <div className="flex flex-col gap-1">
                        <span className="font-medium">Prioritas</span>
                        {PRIORITIES.map((p) => (
                            <label key={p} className="flex items-center gap-2 capitalize">
                                <input type="checkbox" checked={filters.priority.includes(p)} onChange={() => togglePriority(p)} />
                                {p}
                            </label>
                        ))}
                    </div>

                    {hasActiveFilter && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="self-end"
                            onClick={() =>
                                router.get(
                                    route('tasks.board', project.id),
                                    { assignee: [], priority: [] },
                                    { preserveState: true, preserveScroll: true, replace: true },
                                )
                            }
                        >
                            Reset filter
                        </Button>
                    )}
                </div>

                <DndContext sensors={sensors} onDragStart={handleDragStart} onDragEnd={handleDragEnd}>
                    <div className="flex gap-4 overflow-x-auto pb-2">
                        {columns.map((column) => (
                            <DroppableColumn
                                key={column.id}
                                column={column}
                                isDragging={activeCard !== null}
                                isValidTarget={validColumnIds.has(column.id)}
                            >
                                {column.cards.length === 0 ? (
                                    <p className="rounded-md border border-dashed p-4 text-center text-xs text-muted-foreground">
                                        Belum ada tugas.
                                    </p>
                                ) : (
                                    column.cards.map((card) => <DraggableTaskCard key={card.id} projectId={project.id} card={card} />)
                                )}
                            </DroppableColumn>
                        ))}
                    </div>

                    <DragOverlay>{activeCard && <div className="w-72 rounded-md border bg-card p-3 text-sm shadow-lg"><TaskCardContent card={activeCard} /></div>}</DragOverlay>
                </DndContext>
            </div>

            {errorMessage && (
                <div className="fixed bottom-4 left-1/2 z-50 -translate-x-1/2 rounded-md bg-destructive px-4 py-2 text-sm text-white shadow-lg">
                    {errorMessage}
                </div>
            )}
        </AppLayout>
    );
}

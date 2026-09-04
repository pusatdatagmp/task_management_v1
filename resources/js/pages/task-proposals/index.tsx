// ==========================================================
// MODUL       : task-proposals/index
// KLASIFIKASI : UI
// TUJUAN      : F-186 — antrean admin: pengajuan task PENDING dari member,
//               approve (WAJIB review ulang due_date/estimated_minutes/points,
//               keputusan Boss 2026-09-04 — cegah gaming KPI lewat estimasi
//               sendiri milik member) atau reject (alasan wajib).
// DIPANGGIL   : TaskProposalController::index()
// MEMANGGIL   : route('task-proposals.approve'/'task-proposals.reject')
// DATA MASUK  : proposals[] (proposal_status='pending', dari TaskProposalController::index())
// DATA KELUAR : PATCH approve/reject -> TaskProposalController
// RISIKO      : Tombol di sini HANYA HINT UI — gate ASLI (can:task.approve) di
//               middleware routes/admin.php, pola sama extensions/index.tsx.
// ==========================================================

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { promptInput } from '@/lib/swal';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface ProposalRow {
    id: number;
    project: { id: number; name: string };
    title: string;
    description: string | null;
    task_type: string;
    priority_quadrant: string | null;
    due_date: string;
    estimated_minutes: number;
    points: number;
    created_by: { id: number; name: string };
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pengajuan Tugas', href: '/pengajuan-tugas' }];

// SUMBER: datetime-local butuh format "YYYY-MM-DDTHH:mm", ISO dari backend
// (SerializesDatesInAppTimezone, F-72) sudah WIB -- cukup dipotong ke 16 char.
function toDatetimeLocal(value: string): string {
    return value.slice(0, 16);
}

function ProposalCard({ proposal }: { proposal: ProposalRow }) {
    const [dueDate, setDueDate] = useState(toDatetimeLocal(proposal.due_date));
    const [estimatedMinutes, setEstimatedMinutes] = useState(proposal.estimated_minutes);
    const [points, setPoints] = useState(proposal.points);
    const [processing, setProcessing] = useState(false);

    const approve = () => {
        setProcessing(true);
        router.patch(
            route('task-proposals.approve', proposal.id),
            { due_date: dueDate, estimated_minutes: estimatedMinutes, points },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    const reject = async () => {
        const note = await promptInput('Alasan penolakan (wajib diisi):', {
            title: 'Tolak pengajuan',
            validator: (value) => (!value.trim() ? 'Alasan wajib diisi.' : null),
        });
        if (!note) return;

        router.patch(route('task-proposals.reject', proposal.id), { review_note: note }, { preserveScroll: true });
    };

    return (
        <div className="rounded-md border p-3 text-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="font-medium">
                    {proposal.project.name} — {proposal.title}
                </span>
                <span className="text-xs text-muted-foreground">Diajukan oleh {proposal.created_by.name}</span>
            </div>
            {proposal.description && (
                <div className="prose prose-sm mt-1 max-w-none text-muted-foreground" dangerouslySetInnerHTML={{ __html: proposal.description }} />
            )}

            <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div className="grid gap-1">
                    <Label htmlFor={`due-${proposal.id}`}>Due date</Label>
                    <Input id={`due-${proposal.id}`} type="datetime-local" value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor={`est-${proposal.id}`}>Estimasi (menit)</Label>
                    <Input
                        id={`est-${proposal.id}`}
                        type="number"
                        min={1}
                        value={estimatedMinutes}
                        onChange={(e) => setEstimatedMinutes(Number(e.target.value))}
                    />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor={`points-${proposal.id}`}>Poin</Label>
                    <Input id={`points-${proposal.id}`} type="number" min={0} value={points} onChange={(e) => setPoints(Number(e.target.value))} />
                </div>
            </div>
            <p className="mt-2 text-xs text-muted-foreground">
                Diajukan: {new Date(proposal.due_date).toLocaleString('id-ID')} — {proposal.estimated_minutes} menit — {proposal.points} poin
                (bisa dikoreksi di atas sebelum disetujui).
            </p>

            <div className="mt-3 flex gap-2">
                <Button type="button" size="sm" disabled={processing} onClick={approve}>
                    Approve
                </Button>
                <Button type="button" size="sm" variant="outline" disabled={processing} onClick={reject}>
                    Reject
                </Button>
            </div>
        </div>
    );
}

export default function TaskProposalsIndex({ proposals }: { proposals: ProposalRow[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pengajuan Tugas" />

            <div className="flex flex-col gap-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Antrean Pengajuan Tugas ({proposals.length})</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {proposals.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Tidak ada pengajuan menunggu keputusan.</p>
                        ) : (
                            proposals.map((p) => <ProposalCard key={p.id} proposal={p} />)
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

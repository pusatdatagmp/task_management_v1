// ==========================================================
// MODUL       : task-proposals/my-proposals
// KLASIFIKASI : UI
// TUJUAN      : F-186 — riwayat pengajuan task MILIK SENDIRI (pending/rejected).
//               Proposal yang SUDAH di-approve tidak muncul di sini lagi — sudah
//               jadi task biasa, lihat Tugas Saya (TaskProposalController::
//               myProposals() header untuk alasan).
// DIPANGGIL   : TaskProposalController::myProposals()
// MEMANGGIL   : route('task-proposals.create')
// DATA MASUK  : proposals[] (created_by = user login, proposal_status pending/rejected)
// DATA KELUAR : -
// RISIKO      : -
// ==========================================================

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface ProposalRow {
    id: number;
    project: { id: number; name: string };
    title: string;
    due_date: string;
    estimated_minutes: number;
    points: number;
    proposal_status: 'pending' | 'rejected';
    proposal_review_note: string | null;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pengajuan Saya', href: '/my-task-proposals' }];

const statusBadge: Record<ProposalRow['proposal_status'], { label: string; className: string }> = {
    pending: { label: 'Menunggu', className: 'bg-amber-500' },
    rejected: { label: 'Ditolak', className: 'bg-red-600' },
};

export default function MyTaskProposals({ proposals }: { proposals: ProposalRow[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pengajuan Saya" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-lg font-semibold">Pengajuan Tugas Saya</h1>
                    <Button asChild size="sm">
                        <Link href={route('task-proposals.create')}>Ajukan Tugas</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Riwayat Pengajuan</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {proposals.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada pengajuan menunggu/ditolak. Yang sudah disetujui admin ada di Tugas Saya.
                            </p>
                        ) : (
                            proposals.map((p) => (
                                <div key={p.id} className="rounded-md border p-3 text-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-medium">
                                            {p.project.name} — {p.title}
                                        </span>
                                        <Badge className={statusBadge[p.proposal_status].className}>{statusBadge[p.proposal_status].label}</Badge>
                                    </div>
                                    <p className="mt-1 text-muted-foreground">
                                        Tenggat diajukan: {new Date(p.due_date).toLocaleString('id-ID')} — {p.estimated_minutes} menit — {p.points} poin
                                    </p>
                                    {p.proposal_review_note && (
                                        <p className="mt-1 text-muted-foreground">Catatan admin: {p.proposal_review_note}</p>
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

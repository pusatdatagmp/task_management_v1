// ==========================================================
// MODUL       : task-statuses/index
// KLASIFIKASI : UI
// TUJUAN      : Daftar status per project + tabel RADIO/CHECKBOX flag (F-74,
//               Hari-5 §B). "Selesai" & "Review" = radio (struktur menjamin
//               tepat 1 / boleh nol, tidak pernah 0-atau-2). "Sedang dikerjakan"
//               = checkbox (boleh lebih dari satu). SATU submit untuk semua baris.
// DIPANGGIL   : TaskStatusController::index()
// MEMANGGIL   : route('task-statuses.create'/'edit'/'reorder'/'destroy'/'update-flags')
// DATA MASUK  : project {id,name}, statuses[] (urut position)
// DATA KELUAR : navigasi create/edit, PATCH reorder, DELETE destroy, PATCH update-flags
// RISIKO      : SUMBER : tabel flag dibungkus SATU <form>, tapi tombol reorder/
//               edit/hapus HARUS type="button" (Button bawaan default ke
//               type="submit" di dalam <form> HTML) — kalau lupa, klik "Hapus"
//               akan diam-diam ikut submit form flag di atasnya.
// ==========================================================

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import { FormEventHandler } from 'react';

interface StatusRow {
    id: number;
    name: string;
    color: string;
    position: number;
    is_work_state: boolean;
    is_review: boolean;
    is_completed: boolean;
}

export default function TaskStatusesIndex({ project, statuses }: { project: { id: number; name: string }; statuses: StatusRow[] }) {
    // SUMBER: Inertia share otomatis 'errors' dari session setelah redirect gagal
    // (D5 tolak hapus) — reorder/hapus dipicu router.patch/delete langsung, bukan
    // form biasa, jadi errornya ditangkap di sini.
    const { errors } = usePage<{ errors: Record<string, string> }>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Project', href: '/projects' },
        { title: project.name, href: route('projects.edit', project.id) },
        { title: 'Status', href: route('task-statuses.index', project.id) },
    ];

    const { data, setData, patch, processing, errors: flagErrors } = useForm({
        is_completed_id: statuses.find((s) => s.is_completed)?.id ?? ('' as number | ''),
        is_review_id: statuses.find((s) => s.is_review)?.id ?? ('' as number | ''),
        work_state_ids: statuses.filter((s) => s.is_work_state).map((s) => s.id),
    });

    const submitFlags: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('task-statuses.update-flags', project.id), { preserveScroll: true });
    };

    const toggleWorkState = (id: number, checked: boolean) => {
        setData('work_state_ids', checked ? [...data.work_state_ids, id] : data.work_state_ids.filter((w) => w !== id));
    };

    const move = (status: StatusRow, direction: 'up' | 'down') => {
        router.patch(route('task-statuses.reorder', [project.id, status.id]), { direction }, { preserveScroll: true });
    };

    const destroy = (status: StatusRow) => {
        if (confirm(`Hapus status "${status.name}"?`)) {
            router.delete(route('task-statuses.destroy', [project.id, status.id]), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Status — ${project.name}`} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Status: {project.name}</h1>
                    <Button asChild>
                        <Link href={route('task-statuses.create', project.id)}>Status Baru</Link>
                    </Button>
                </div>

                {errors.status && (
                    <Alert variant="destructive">
                        <AlertDescription>{errors.status}</AlertDescription>
                    </Alert>
                )}
                {(flagErrors.is_completed_id || flagErrors.work_state_ids) && (
                    <Alert variant="destructive">
                        <AlertDescription>{flagErrors.is_completed_id ?? flagErrors.work_state_ids}</AlertDescription>
                    </Alert>
                )}

                <form onSubmit={submitFlags}>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-muted-foreground">
                                    <th className="p-3">Urutan</th>
                                    <th className="p-3">Nama</th>
                                    <th className="p-3 text-center">Penanda selesai</th>
                                    <th className="p-3 text-center">Butuh review</th>
                                    <th className="p-3 text-center">Counter jalan</th>
                                    <th className="p-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {statuses.map((status, index) => (
                                    <tr key={status.id} className="border-b last:border-0">
                                        <td className="p-3">
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    className="size-7"
                                                    disabled={index === 0}
                                                    onClick={() => move(status, 'up')}
                                                >
                                                    <ArrowUp className="size-3.5" />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    className="size-7"
                                                    disabled={index === statuses.length - 1}
                                                    onClick={() => move(status, 'down')}
                                                >
                                                    <ArrowDown className="size-3.5" />
                                                </Button>
                                            </div>
                                        </td>
                                        <td className="p-3">
                                            <span className="inline-flex items-center gap-2">
                                                <span className="size-3 rounded-full" style={{ backgroundColor: status.color }} />
                                                {status.name}
                                            </span>
                                        </td>
                                        {/* F-74: RADIO — struktur HTML sendiri yang menjamin tepat 1 terpilih per name group */}
                                        <td className="p-3 text-center">
                                            <input
                                                type="radio"
                                                name="is_completed_id"
                                                aria-label={`Jadikan ${status.name} status selesai`}
                                                checked={data.is_completed_id === status.id}
                                                onChange={() => setData('is_completed_id', status.id)}
                                                required
                                            />
                                        </td>
                                        <td className="p-3 text-center">
                                            <input
                                                type="radio"
                                                name="is_review_id"
                                                aria-label={`Jadikan ${status.name} status review`}
                                                checked={data.is_review_id === status.id}
                                                onChange={() => setData('is_review_id', status.id)}
                                            />
                                        </td>
                                        {/* F-41/F-74: CHECKBOX — boleh lebih dari satu, minimal 1 di seluruh tabel */}
                                        <td className="p-3 text-center">
                                            <input
                                                type="checkbox"
                                                aria-label={`${status.name} termasuk sedang dikerjakan`}
                                                checked={data.work_state_ids.includes(status.id)}
                                                onChange={(e) => toggleWorkState(status.id, e.target.checked)}
                                            />
                                        </td>
                                        <td className="p-3">
                                            <div className="flex gap-2">
                                                <Button type="button" variant="outline" size="sm" asChild>
                                                    <Link href={route('task-statuses.edit', [project.id, status.id])}>Edit</Link>
                                                </Button>
                                                <Button type="button" variant="outline" size="sm" onClick={() => destroy(status)}>
                                                    Hapus
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-3 flex items-center gap-4">
                        <Button disabled={processing}>Simpan Flag</Button>
                        {data.is_review_id !== '' && (
                            <button
                                type="button"
                                className="text-sm text-muted-foreground underline"
                                onClick={() => setData('is_review_id', '')}
                            >
                                Kosongkan pilihan "butuh review" (boleh tidak ada)
                            </button>
                        )}
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

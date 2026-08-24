/**
 * ==========================================================
 * MODUL       : review-notice.tsx
 * KLASIFIKASI : UI
 * TUJUAN      : Permintaan Boss (2026-08-22) — indikator "Review" di header,
 *               sebelah bell notifikasi. Beda dari NotificationBell (riwayat
 *               notifikasi tersimpan): badge di sini LIVE COUNT tugas berstatus
 *               Review SEKARANG (SharedData.reviewTasksCount, F-38 nol riwayat
 *               tersimpan). Bentuk & interaksi SENGAJA DISAMAKAN NotificationBell
 *               (revisi 2026-08-22, permintaan Boss) — ikon + badge, klik buka
 *               dropdown daftar tugas Review (bukan langsung navigasi seperti
 *               versi pertama). Klik SATU baris di dropdown -> ke detail tugas
 *               itu (route tasks.show).
 * DIPANGGIL   : app-sidebar-header.tsx (header aktif — app-header.tsx TIDAK
 *               dipakai layout mana pun, lihat app-layout.tsx -> app-sidebar-layout.tsx)
 * MEMANGGIL   : GET route('reviews.index') (TaskController::reviewList(), JSON)
 *               — dropdown butuh fetch async tanpa navigasi, pola SAMA notification-bell.tsx
 * DATA MASUK  : SharedData.reviewTasksCount (dishare tiap halaman, badge awal —
 *               bisa basi sampai navigasi berikutnya, SAMA seperti unreadNotificationsCount)
 * DATA KELUAR : Navigasi ke detail tugas (tasks.show) saat baris diklik.
 *               localStorage key 'reviewTasksSeenCount' (murni state tampilan
 *               klien, BUKAN entitas KPI/DB — F-4, sekadar "sudah saya lihat")
 * RISIKO      : count NULL (dua permission task.approve+project.viewAll di
 *               HandleInertiaRequests TIDAK lengkap) -> komponen TIDAK RENDER
 *               apa pun. count 0 (BERWENANG, nol tugas Review) TETAP tampil
 *               ikon+badge "0" warna netral (permintaan Boss). Badge BIRU
 *               selama ada tugas Review yang BELUM PERNAH dibuka dropdown-nya,
 *               balik netral begitu dropdown dibuka (localStorage per browser,
 *               TIDAK sinkron lintas device — sesuai definisi Boss).
 * ==========================================================
 */
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const SEEN_KEY = 'reviewTasksSeenCount';

interface ReviewTaskItem {
    id: number;
    project_id: number;
    title: string;
    project_name: string | null;
}

export function ReviewNotice() {
    const { props } = usePage<SharedData>();
    const count = props.reviewTasksCount;
    // GUARD: default state SAMA DENGAN count (anggap "sudah dilihat") supaya
    // render pertama (sebelum useEffect baca localStorage) tidak sempat
    // ke-flash biru lalu balik netral -- localStorage cuma bisa dibaca di efek
    // client-side, bukan saat render awal.
    const [seen, setSeen] = useState(count ?? 0);
    const [isOpen, setIsOpen] = useState(false);
    const [tasks, setTasks] = useState<ReviewTaskItem[] | null>(null);
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const stored = Number(localStorage.getItem(SEEN_KEY) ?? '0');
        setSeen(stored);
        // GUARD: hanya jalan saat MOUNT -- perubahan `count` antar navigasi
        // (mis. ada task baru masuk Review) SENGAJA tidak memicu re-baca
        // localStorage, supaya perbandingan `count > seen` di bawah tetap
        // pakai baseline "terakhir dibuka", bukan ke-reset diam-diam.
    }, []);

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // GUARD: null = TIDAK berwenang -- sembunyikan TOTAL. 0 (berwenang, nol
    // tugas Review) SENGAJA TETAP LOLOS ke render di bawah (permintaan Boss).
    if (count === null) {
        return null;
    }

    // WORKAROUND: TypeScript TIDAK mempersempit `number | null` -> `number`
    // lintas closure `function` biasa (beda dari narrowing linear biasa) --
    // `reviewCount` di sini sudah pasti `number` (lolos guard di atas),
    // dipakai di dalam openDropdown()/JSX di bawah supaya TS tidak komplain.
    const reviewCount = count;
    const isUnseen = reviewCount > seen;

    function openDropdown() {
        setIsOpen((prev) => !prev);

        if (!isOpen) {
            fetch(route('reviews.index'), { headers: { Accept: 'application/json' } })
                .then((res) => res.json())
                .then((data: { tasks: ReviewTaskItem[] }) => setTasks(data.tasks));

            // SUMBER (permintaan Boss): buka dropdown = "sudah dilihat" --
            // badge balik netral, TIDAK menunggu klik salah satu baris.
            localStorage.setItem(SEEN_KEY, String(reviewCount));
            setSeen(reviewCount);
        }
    }

    function handleClickTask(task: ReviewTaskItem) {
        setIsOpen(false);
        router.visit(`/projects/${task.project_id}/tasks/${task.id}`);
    }

    return (
        <div ref={containerRef} className="relative">
            {/* Permintaan Boss (2026-08-22): hover tooltip -- pola SAMA
                notification-bell.tsx/app-header.tsx. */}
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            onClick={openDropdown}
                            className="relative flex h-9 w-9 items-center justify-center rounded-md hover:bg-accent"
                            aria-label="Review"
                        >
                            <ClipboardCheck className="h-5 w-5" />
                            <span
                                className={`absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-medium ${
                                    isUnseen ? 'bg-blue-500 text-white' : 'bg-muted text-muted-foreground'
                                }`}
                            >
                                {reviewCount > 99 ? '99+' : reviewCount}
                            </span>
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>Review</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>

            {isOpen && (
                <div className="absolute top-full right-0 z-50 mt-1 w-80 rounded-md border bg-popover shadow-md">
                    <div className="flex items-center justify-between border-b px-3 py-2">
                        <span className="text-sm font-medium">Review</span>
                    </div>

                    {tasks === null ? (
                        <div className="p-3 text-sm text-muted-foreground">Memuat...</div>
                    ) : tasks.length === 0 ? (
                        <div className="p-3 text-sm text-muted-foreground">Tidak ada tugas menunggu review</div>
                    ) : (
                        <ul className="max-h-96 overflow-y-auto py-1">
                            {tasks.map((task) => (
                                <li key={task.id}>
                                    <button
                                        type="button"
                                        onClick={() => handleClickTask(task)}
                                        className="flex w-full flex-col gap-0.5 px-3 py-2 text-left text-sm hover:bg-accent"
                                    >
                                        <span>{task.title}</span>
                                        <span className="text-xs text-muted-foreground">{task.project_name}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}

                    {/* SUMBER: endpoint reviewList() cuma kirim 10 teratas (F-85) --
                        REUSE SharedData.reviewTasksCount (sudah ada di semua halaman,
                        nol query dobel) untuk tahu ada sisa yang tidak muncul di sini. */}
                    {tasks !== null && reviewCount > tasks.length && (
                        <div className="border-t px-3 py-2 text-center">
                            <button
                                type="button"
                                onClick={() => {
                                    setIsOpen(false);
                                    router.get(route('tasks.all'), { status_flag: ['review'] });
                                }}
                                className="text-xs text-muted-foreground hover:underline"
                            >
                                Lihat semua →
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

/**
 * ==========================================================
 * MODUL       : notification-bell.tsx
 * KLASIFIKASI : UI
 * TUJUAN      : Bell + dropdown notifikasi di header (F-35 §C4).
 * DIPANGGIL   : app-sidebar-header.tsx
 * MEMANGGIL   : GET route('notifications.index'), PATCH route('notifications.read'),
 *               POST route('notifications.read-all') — JSON, bukan Inertia::render
 * DATA MASUK  : SharedData.unreadNotificationsCount (badge awal, dishare tiap halaman)
 * DATA KELUAR : Navigasi ke halaman detail task (tasks.show, F-82) saat notifikasi diklik
 * RISIKO      : Badge awal dari props Inertia (bisa basi sampai navigasi berikutnya);
 *               begitu dropdown dibuka, jumlah di-refresh dari endpoint langsung.
 * ==========================================================
 */
import { router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { type SharedData } from '@/types';

interface NotificationItem {
    id: string;
    type: string | null;
    message: string;
    task_id: number | null;
    project_id: number | null;
    read_at: string | null;
    created_at: string;
}

/**
 * WORKAROUND: fetch() bawaan tidak otomatis mengirim CSRF seperti axios (yang
 * dipakai Inertia router secara internal). Laravel set cookie XSRF-TOKEN
 * (readable, bukan httpOnly) untuk request stateful — dibaca manual di sini
 * dan dikirim sebagai header X-XSRF-TOKEN, cara yang sama seperti axios.
 */
function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
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

export function NotificationBell() {
    const { props } = usePage<SharedData>();
    const [isOpen, setIsOpen] = useState(false);
    const [notifications, setNotifications] = useState<NotificationItem[] | null>(null);
    const [unreadCount, setUnreadCount] = useState(props.unreadNotificationsCount);
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setUnreadCount(props.unreadNotificationsCount);
    }, [props.unreadNotificationsCount]);

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    function openDropdown() {
        setIsOpen((prev) => !prev);

        if (!isOpen) {
            fetch('/notifications', { headers: { Accept: 'application/json' } })
                .then((res) => res.json())
                .then((data: { notifications: NotificationItem[]; unread_count: number }) => {
                    setNotifications(data.notifications);
                    setUnreadCount(data.unread_count);
                });
        }
    }

    function handleClickNotification(notification: NotificationItem) {
        setIsOpen(false);

        fetch(`/notifications/${notification.id}/read`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
        }).then(() => {
            setUnreadCount((prev) => Math.max(0, prev - 1));

            if (notification.project_id && notification.task_id) {
                router.visit(`/projects/${notification.project_id}/tasks/${notification.task_id}`);
            }
        });
    }

    function handleMarkAllRead() {
        fetch('/notifications/read-all', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
        }).then(() => {
            setUnreadCount(0);
            setNotifications((prev) => prev?.map((n) => ({ ...n, read_at: new Date().toISOString() })) ?? null);
        });
    }

    return (
        <div ref={containerRef} className="relative">
            <button
                type="button"
                onClick={openDropdown}
                className="relative flex h-9 w-9 items-center justify-center rounded-md hover:bg-accent"
                aria-label="Notifikasi"
            >
                <Bell className="h-5 w-5" />
                {unreadCount > 0 && (
                    /* F-143: bg-red-500 hardcode -> token destructive (sudah ada,
                       setara semantik "urgent") supaya ikut tema, bukan warna tetap. */
                    <span className="bg-destructive text-destructive-foreground absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-medium">
                        {unreadCount > 99 ? '99+' : unreadCount}
                    </span>
                )}
            </button>

            {isOpen && (
                <div className="absolute top-full right-0 z-50 mt-1 w-80 rounded-md border bg-popover shadow-md">
                    <div className="flex items-center justify-between border-b px-3 py-2">
                        <span className="text-sm font-medium">Notifikasi</span>
                        {unreadCount > 0 && (
                            <button type="button" onClick={handleMarkAllRead} className="text-xs text-muted-foreground hover:underline">
                                Tandai semua dibaca
                            </button>
                        )}
                    </div>

                    {notifications === null ? (
                        <div className="p-3 text-sm text-muted-foreground">Memuat...</div>
                    ) : notifications.length === 0 ? (
                        <div className="p-3 text-sm text-muted-foreground">Belum ada notifikasi</div>
                    ) : (
                        <ul className="max-h-96 overflow-y-auto py-1">
                            {notifications.map((notification) => (
                                <li key={notification.id}>
                                    <button
                                        type="button"
                                        onClick={() => handleClickNotification(notification)}
                                        className={`flex w-full flex-col gap-0.5 px-3 py-2 text-left text-sm hover:bg-accent ${
                                            notification.read_at ? 'opacity-60' : ''
                                        }`}
                                    >
                                        <span>{notification.message}</span>
                                        <span className="text-xs text-muted-foreground">{timeAgo(notification.created_at)}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}

import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CalendarOff,
    CheckSquare,
    Clock,
    Facebook,
    Folder,
    History,
    Hourglass,
    Instagram,
    LayoutGrid,
    Linkedin,
    ListChecks,
    MessageCircle,
    Repeat,
    Settings,
    Trophy,
    Users,
} from 'lucide-react';
import AppLogo from './app-logo';



export function AppSidebar() {
    // F-90: sembunyikan menu per PERMISSION (03-BUSINESS-FLOW §6), bukan boolean
    // isAdmin — role custom dengan user.manage tapi bukan workschedule.manage
    // (mis.) akan lihat menu User tapi bukan Jam Kerja. Ini HANYA gating
    // tampilan — penegakan sebenarnya di middleware `can:xxx` server-side.
    const { auth, branding, version } = usePage<SharedData>().props;
    const can = (permission: string) => auth.permissions.includes(permission);

    // F-142 (v1.2 DS-2): link sosmed/wa Branding org -- reuse NavFooter (sudah
    // ADA di codebase, sebelumnya tak pernah dipakai) alih-alih komponen baru.
    // Cuma link yang DIISI Boss yang muncul (bukan 4 slot kosong).
    const brandingFooterItems: NavItem[] = [
        ...(branding?.wa_number ? [{ title: 'WhatsApp', url: `https://wa.me/${branding.wa_number.replace(/\D/g, '')}`, icon: MessageCircle }] : []),
        ...(branding?.facebook_url ? [{ title: 'Facebook', url: branding.facebook_url, icon: Facebook }] : []),
        ...(branding?.instagram_url ? [{ title: 'Instagram', url: branding.instagram_url, icon: Instagram }] : []),
        ...(branding?.linkedin_url ? [{ title: 'LinkedIn', url: branding.linkedin_url, icon: Linkedin }] : []),
    ];

    // F-144 §12.2: pembagi grup admin (RINGKASAN/KERJA/ORGANISASI) vs member
    // (KERJA SAYA saja). Reuse `dashboard.view` — SUDAH jadi batas admin/member
    // yang sama dipakai `homeUrl` di bawah (F-95), bukan konsep role baru.
    const isAdminNav = can('dashboard.view');

    // v1.2 H4 (F-121): nav "Dashboard" mengarah ke Command Center baru
    // ('dashboard/overview') yang MENAMBAH widget agregasi di sekitar dashboard
    // 3-angka lama (section "Beban Tim") -- halaman lama di URL '/dashboard'
    // TETAP hidup (tidak dihapus), cuma tidak lagi jadi target nav utama.
    const ringkasanItems: NavItem[] = [
        ...(can('dashboard.view') ? [{ title: 'Dashboard', url: '/dashboard/overview', icon: LayoutGrid }] : []),
        // v1.2/v1.5 (F-134/F-141): Leaderboard MANAGEMENT-ONLY — permission
        // leaderboard.view NOL default (admin TERMASUK, lihat RolePermissionSeeder).
        // Item ini TIDAK tampil sampai Boss assign manual lewat Role Management
        // (F-135) — member/admin biasa tidak boleh tahu menu ini ada.
        ...(can('leaderboard.view') ? [{ title: 'Leaderboard', url: '/leaderboard', icon: Trophy }] : []),
    ];

    // F-144 §12.2 KERJA (admin): item pribadi (Tugas Saya/Perpanjangan Saya)
    // diselipkan di sini, BUKAN dihilangkan dari nav admin — admin juga bisa
    // jadi assignee task (lihat Task::assignees(), DatabaseSeeder), kalau nav-nya
    // dihapus itu regresi (F-121 ADD-DON'T-DELETE). Dikonfirmasi Boss saat LANJUT.
    const kerjaItems: NavItem[] = [
        { title: 'Proyek', url: '/projects', icon: Folder },
        { title: 'Tugas Saya', url: '/my-tasks', icon: CheckSquare },
        // v1.2 H7b (F-140/F-144/F-147): halaman lintas-proyek sudah dibangun —
        // digerbangi permission KONKRET (F-90), sama seperti route-nya di
        // routes/admin.php, BUKAN blanket "admin boleh semua".
        ...(can('project.viewAll') ? [{ title: 'Semua Tugas', url: '/tasks', icon: ListChecks }] : []),
        ...(can('task.manage') ? [{ title: 'Tugas Berulang', url: '/task-templates', icon: Repeat }] : []),
        ...(can('task.approve') ? [{ title: 'Perpanjangan', url: '/pengaturan/perpanjangan', icon: CalendarClock }] : []),
        // v0.8 H6 (F-50): "ajukan" tersedia admin & member (matriks BF §6), jadi
        // link ini SELALU tampil, tidak digerbangi permission (F-95 — gating
        // assignee, bukan RBAC).
        { title: 'Perpanjangan Saya', url: '/my-extensions', icon: Hourglass },
    ];

    const organisasiItems: NavItem[] = [
        ...(can('user.manage') ? [{ title: 'Pengguna & Peran', url: '/pengaturan/users', icon: Users }] : []),
        ...(can('workschedule.manage')
            ? [
                  { title: 'Jam Kerja', url: '/pengaturan/jam-kerja', icon: Clock },
                  { title: 'Hari Libur', url: '/pengaturan/hari-libur', icon: CalendarOff },
              ]
            : []),
        // v1.0 H4 (F-116): log GLOBAL — permission activity.view (admin default),
        // BUKAN ditampilkan ke member biasa.
        ...(can('activity.view') ? [{ title: 'Log Activity', url: '/pengaturan/activity-log', icon: History }] : []),
        // v1.2 DS-2 (F-142/F-147): branding aktif (tab Tema DS-3 menyusul di
        // halaman yang sama) — tidak lagi placeholder, F-147 tutup penuh.
        ...(can('settings.manage') ? [{ title: 'Setelan', url: '/pengaturan/setelan', icon: Settings }] : []),
    ];

    // F-95: member = nol permission → hanya lihat tugas/proyek/perpanjangan
    // miliknya sendiri (gating assignee/membership di controller, BUKAN RBAC).
    const kerjaSayaItems: NavItem[] = [
        { title: 'Tugas Saya', url: '/my-tasks', icon: CheckSquare },
        { title: 'Proyek Saya', url: '/projects', icon: Folder },
        { title: 'Perpanjangan Saya', url: '/my-extensions', icon: Hourglass },
    ];

    // SUMBER: klik logo = "pulang" ke landing masing-masing role, sama seperti
    // AuthenticatedSessionController::store() setelah login (F-95) -- member
    // tidak boleh diarahkan ke /dashboard (403), jadi ikut /my-tasks. Admin ikut
    // nav "Dashboard" (v1.2 H4) -- /dashboard/overview, bukan /dashboard lama.
    const homeUrl = isAdminNav ? '/dashboard/overview' : '/my-tasks';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            {/* F-169: TANPA prefetch -- lihat komentar nav-main.tsx. */}
                            <Link href={homeUrl}>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {isAdminNav ? (
                    <>
                        {ringkasanItems.length > 0 && <NavMain label="Overview" items={ringkasanItems} />}
                        <NavMain label="Task" items={kerjaItems} />
                        <NavMain label="Setting" items={organisasiItems} />
                    </>
                ) : (
                    <NavMain label="Kerja Saya" items={kerjaSayaItems} />
                )}
            </SidebarContent>

            <SidebarFooter>
                {/* F-142: alamat = teks (bukan link), sosmed/wa = NavFooter (link,
                    buka tab baru). Cuma tampil kalau Boss sudah isi Setelan. */}
                {branding?.address && (
                    <p className="px-2 pb-1 text-xs text-sidebar-foreground/70 group-data-[collapsible=icon]:hidden">{branding.address}</p>
                )}
                {brandingFooterItems.length > 0 && <NavFooter items={brandingFooterItems} className="mt-0" />}
                <NavUser />
                {/* Permintaan Boss (2026-08-10, F-169): label versi sistem --
                    sekadar info build, disembunyikan otomatis saat sidebar
                    di-collapse ke mode ikon (pola sama alamat branding di atas). */}
                <p className="px-2 pt-1 text-center text-[10px] text-sidebar-foreground/50 group-data-[collapsible=icon]:hidden">{version}</p>
            </SidebarFooter>
        </Sidebar>
    );
}

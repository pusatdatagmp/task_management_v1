import { type ThemeConfig } from '@/lib/theme-tokens';
import { LucideIcon } from 'lucide-react';

// F-90/RBAC §D3: daftar NAMA permission (mis. 'task.manage'), BUKAN boolean
// isAdmin/role string — komponen cek `auth.permissions.includes('xxx')`,
// TIDAK PERNAH hardcode nama role (F-44-style, tapi untuk role).
export interface Auth {
    user: User;
    permissions: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    // F-144 §12.2: item tampil di grup tapi belum ada halaman/route-nya --
    // ditandai "Segera", tak bisa diklik. Murni penanda visual, bukan
    // permission gate. Semua item nav sekarang aktif (F-147 tutup penuh,
    // v1.2 DS-2) -- flag ini dipertahankan untuk item masa depan yang mungkin
    // butuh state sama.
    disabled?: boolean;
    // Permintaan Boss (2026-08-22): badge angka gaya notifikasi (mis. "Tugas
    // Saya" -> SharedData.myTasksCount) -- undefined/0 = badge disembunyikan
    // (lihat NavMain), bukan tampil "0".
    badge?: number;
}

// F-142 (v1.2 DS-2): custom branding org (BUKAN identitas tenant `organizations.
// name`/`slug` internal). null = org belum isi apa pun -- FRONTEND yang render
// fallback default TEMPO, bukan backend yang paksa isi placeholder ke DB.
export interface Branding {
    company_name: string | null;
    address: string | null;
    wa_number: string | null;
    facebook_url: string | null;
    instagram_url: string | null;
    linkedin_url: string | null;
    logo_url: string | null;
}

export interface SharedData {
    name: string;
    // F-169 (2026-08-10): label versi sistem, tampil footer sidebar.
    version: string;
    quote: { message: string; author: string };
    auth: Auth;
    unreadNotificationsCount: number;
    // Permintaan Boss (2026-08-22): badge sidebar "Tugas Saya" -- SATU SUMBER
    // dengan TaskController::myTasks() (assignee=user login, belum selesai),
    // dishare GLOBAL (pola sama unreadNotificationsCount) karena sidebar
    // dirender di setiap halaman (lihat HandleInertiaRequests::share()).
    myTasksCount: number;
    // Permintaan Boss (2026-08-22): indikator "Review" header (sebelah bell
    // notifikasi) -- LIVE COUNT tugas berstatus Review. null = TIDAK berwenang
    // (sembunyikan total, lihat HandleInertiaRequests::share()); 0 = berwenang
    // tapi nol tugas Review (TETAP tampil "Review · 0", revisi 2026-08-22).
    reviewTasksCount: number | null;
    // Permintaan Boss (2026-08-22): badge sidebar "Perpanjangan" -- SATU SUMBER
    // dengan DeadlineExtensionController::index() (status='pending'), dishare
    // GLOBAL (pola sama myTasksCount) karena sidebar dirender di setiap halaman.
    pendingExtensionsCount: number;
    // F-186 (keputusan Boss 2026-09-04): badge sidebar "Pengajuan Tugas" (admin)
    // -- SATU SUMBER dengan TaskProposalController::index() (proposal_status=
    // 'pending'), pola sama pendingExtensionsCount.
    pendingTaskProposalsCount: number;
    branding: Branding | null;
    // F-143 (v1.2 DS-3): null = org belum kustom tema -- CSS default TEMPO
    // (app.css) yang berlaku, F-145 fallback aman.
    theme: ThemeConfig | null;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    // Permintaan Boss (2026-08-22): SELALU terisi (User::displayName() backend,
    // fallback ke `name` kalau nickname kosong) -- komponen tampilan pakai INI,
    // bukan `name` langsung, supaya nama panggilan otomatis kepakai begitu diisi.
    display_name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

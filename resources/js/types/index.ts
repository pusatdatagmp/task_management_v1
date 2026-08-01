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
    quote: { message: string; author: string };
    auth: Auth;
    unreadNotificationsCount: number;
    branding: Branding | null;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

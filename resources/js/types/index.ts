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
    // F-144 §12.2: item tampil di grup tapi belum ada halaman/route-nya
    // (mis. Semua Tugas, Tugas Berulang, Setelan) -- ditandai "Segera",
    // tak bisa diklik. Murni penanda visual, bukan permission gate.
    disabled?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    unreadNotificationsCount: number;
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

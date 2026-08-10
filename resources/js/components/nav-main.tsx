import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

// F-144 §12.2: satu <NavMain> = satu grup sidebar berlabel (RINGKASAN/KERJA/
// ORGANISASI/KERJA SAYA). app-sidebar.tsx merender beberapa instance ini.
export function NavMain({ label, items = [] }: { label: string; items: NavItem[] }) {
    const page = usePage();
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) =>
                    item.disabled ? (
                        // F-140/F-144: route/halamannya belum dibangun (celah blueprint
                        // diakui) -- tampil sesuai §12.2 tapi non-klik, nol route baru.
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton disabled aria-disabled="true" className="cursor-not-allowed opacity-50">
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                                <span className="ml-auto text-[10px] tracking-wide text-sidebar-foreground/60 uppercase">Segera</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ) : (
                        <SidebarMenuItem key={item.title}>
                            {/* F-169 (audit Boss 2026-08-10): TANPA prefetch -- Inertia v2
                                prefetch-on-hover cache respons 30 detik, jadi kalau data
                                berubah (mis. approve task) lalu pindah halaman lewat
                                sidebar, yang tampil bisa BASI sampai user refresh manual.
                                App performance-management ini butuh data SELALU akurat
                                > transisi terasa instan. */}
                            <SidebarMenuButton asChild isActive={item.url === page.url}>
                                <Link href={item.url}>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}

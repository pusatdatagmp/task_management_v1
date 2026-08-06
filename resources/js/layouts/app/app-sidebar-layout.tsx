import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    // SUMBER: key = nama KOMPONEN halaman (Inertia `component`, mis. 'tasks/all'),
    // BUKAN `url` penuh -- url berubah tiap filter/query-string (router.get
    // preserveState) yang TIDAK boleh memicu transisi fade-halaman penuh, cuma
    // navigasi ke KOMPONEN beda yang harus terasa "pindah halaman". Radix
    // Dialog/Dropdown/Sheet SENGAJA tidak disentuh (animasi bawaan tailwindcss-
    // animate tetap dipakai, permintaan Boss) -- ini murni area yang BELUM
    // teranimasi sebelumnya.
    const { component } = usePage();

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <AnimatePresence initial={false}>
                    <motion.div
                        key={component}
                        initial={{ opacity: 0, y: 8 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: 0.15, ease: 'easeOut' }}
                    >
                        {children}
                    </motion.div>
                </AnimatePresence>
            </AppContent>
        </AppShell>
    );
}

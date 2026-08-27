import { Breadcrumbs } from '@/components/breadcrumbs';
import { GlobalSearch } from '@/components/global-search';
import { NotificationBell } from '@/components/notification-bell';
import { PushPermissionPrompt } from '@/components/push-permission-prompt';
import { ReviewNotice } from '@/components/review-notice';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    return (
        // F-144: header ini duduk di WORKSPACE terang (AppContent), sejajar
        // AppSidebar, bukan di dalamnya -- pakai token border workspace, bukan
        // border-sidebar-border (navy) supaya tak muncul garis gelap di topbar terang.
        <header className="border-border flex h-16 shrink-0 items-center gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="ml-auto flex items-center gap-4">
                <GlobalSearch />
                {/* Permintaan Boss (2026-08-22): indikator "Review" -- SEBELAH
                    (sebelum) bell notifikasi, lihat review-notice.tsx. */}
                <ReviewNotice />
                <NotificationBell />
                {/* F-184/F-185 (permintaan Boss): ajakan aktifkan FCM -- dekat bell/review,
                    render null sendiri kalau tidak relevan (lihat guard di komponennya). */}
                <PushPermissionPrompt />
            </div>
        </header>
    );
}

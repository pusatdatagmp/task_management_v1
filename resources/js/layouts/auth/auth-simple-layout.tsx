import AppLogo from '@/components/app-logo';
import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

// F-169 (audit 2026-08-10): SEBELUMNYA pakai AppLogoIcon (SVG statis, tak
// pernah dimigrasi ke branding dinamis F-142 -- beda dari welcome.tsx/
// errors/error.tsx yang sudah pakai AppLogo). Halaman login Boss selalu
// tampil logo generik walau branding sudah di-setel. AppLogo sendiri yang
// baca prop `branding` (HandleInertiaRequests, sekarang fallback ke
// Organization::first() untuk guest -- lihat F-169 di middleware).
export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link href={route('home')} className="flex flex-col items-center gap-2 font-medium">
                            <AppLogo />
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-muted-foreground text-center text-sm">{description}</p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}

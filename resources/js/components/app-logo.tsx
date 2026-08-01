import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    // F-142 (v1.2 DS-2): logo+nama DINAMIS dari Branding org (dishare global
    // lewat HandleInertiaRequests, F-142) -- null/kosong = org belum pernah isi
    // Setelan, fallback ke asset default TEMPO (`/real.webp`) + teks "TEMPO",
    // BUKAN dikosongkan/rusak.
    const { branding } = usePage<SharedData>().props;
    const logoSrc = branding?.logo_url || '/real.webp';
    const displayName = branding?.company_name || 'TEMPO';

    return (
        <>
            <div className=" text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-md">
                {/* F-144: fill ikut --sidebar-primary-foreground (token), bukan
                    text-white/dark:text-black — sidebar TEMPO tak toggle dark/light
                    (§12.1), jadi kontras ikon tak boleh bergantung pada mode itu. */}
                <img src={logoSrc} alt="" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-none font-semibold">{displayName}</span>
            </div>
        </>
    );
}

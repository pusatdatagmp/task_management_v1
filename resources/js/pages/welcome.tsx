// ==========================================================
// MODUL       : welcome
// KLASIFIKASI : UI
// TUJUAN      : Halaman utama sebelum login. Permintaan Boss (2026-08-07):
//               ganti total dari starter-kit Laravel default (logo Laravel
//               raksasa + link ke docs/Laracasts/Deploy) jadi tampilan
//               modern & elegan milik produk sendiri, NOL branding Laravel.
//               Pakai AppLogo (branding org dinamis, F-142 -- BUKAN logo
//               Laravel) + token warna --tempo-* yang SAMA dipakai seluruh
//               app (F-143/F-144) supaya konsisten & otomatis ikut kalau
//               Boss ganti warna brand di Setelan.
// DIPANGGIL   : routes/web.php ('/') -- guest & user login (Header berubah
//               "Masuk" <-> "Buka Dashboard")
// MEMANGGIL   : route('login'/'dashboard.overview')
// DATA MASUK  : SharedData.auth (status login), SharedData.branding (nama
//               perusahaan utk footer, F-142)
// DATA KELUAR : navigasi ke login/dashboard.overview (Command Center)
// RISIKO      : BUG FIX (permintaan Boss 2026-08-07) -- tombol "Buka Dashboard"
//               sebelumnya route('dashboard') (dashboard 3-angka LAMA), bukan
//               'dashboard.overview' (Command Center) yang jadi front-door
//               sejak v1.2 H4 (nav sidebar sudah ke situ, app-sidebar.tsx:58).
// ==========================================================

import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Welcome() {
    const { auth, branding } = usePage<SharedData>().props;
    const companyName = branding?.company_name || 'TEMPO';

    return (
        <>
            <Head title="Selamat Datang">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
            </Head>
            <div className="flex min-h-svh flex-col bg-background text-foreground">
                <header className="border-b">
                    <div className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-4">
                        <div className="flex items-center">
                            <AppLogo />
                        </div>
                        <div className="flex items-center gap-2">
                            <AppearanceToggleDropdown />
                            <Button asChild>
                                <Link href={auth.user ? route('dashboard.overview') : route('login')}>{auth.user ? 'Buka Dashboard' : 'Masuk'}</Link>
                            </Button>
                        </div>
                    </div>
                </header>

                <main className="flex flex-1 flex-col">
                    <section className="mx-auto flex w-full max-w-6xl flex-1 flex-col items-center justify-center gap-6 px-6 py-24 text-center">
                        <span className="rounded-full border px-4 py-1 text-xs font-medium text-muted-foreground">
                            Task &amp; Performance Management
                        </span>
                        <h1 className="max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                            Kelola tugas dan kinerja tim, lebih rapi dan terukur.
                        </h1>
                        <p className="max-w-xl text-base text-balance text-muted-foreground sm:text-lg">
                            Satu tempat untuk mengatur tugas, memantau beban kerja tim, dan menjalankan tugas berulang secara otomatis.
                        </p>
                        <div className="flex flex-wrap items-center justify-center gap-3">
                            <Button size="lg" asChild>
                                <Link href={auth.user ? route('dashboard.overview') : route('login')}>
                                    {auth.user ? 'Buka Dashboard' : 'Masuk ke Aplikasi'}
                                </Link>
                            </Button>
                        </div>
                    </section>
                </main>

                <footer className="border-t px-6 py-6 text-center text-xs text-muted-foreground">
                    © {new Date().getFullYear()} {companyName}. Seluruh hak cipta dilindungi.
                </footer>
            </div>
        </>
    );
}

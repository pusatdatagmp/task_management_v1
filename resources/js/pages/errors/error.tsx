// ==========================================================
// MODUL       : errors/error
// KLASIFIKASI : UI
// TUJUAN      : Permintaan Boss (2026-08-07) — halaman error 403/404/500
//               profesional, gantikan halaman default Laravel/Symfony yang
//               polos. SATU komponen generik (bukan 1 file per status) --
//               beda status cuma beda ikon/judul/pesan/tombol aksi, struktur
//               visual identik, nol duplikasi markup.
// DIPANGGIL   : bootstrap/app.php withExceptions() -> Inertia::render('errors/error', ['status' => ...])
// MEMANGGIL   : route('home') (tombol "Kembali ke Beranda")
// DATA MASUK  : status (403 | 404 | 500) dari backend, SATU-SATUNYA prop
// DATA KELUAR : navigasi (Link ke beranda) atau reload penuh (tombol 500)
// RISIKO      : Halaman ini bisa dirender TANPA user login (403 dari middleware
//               auth, atau 404 sebelum autentikasi) -- SENGAJA standalone
//               (tanpa AppLayout/sidebar), pola sama auth-simple-layout.tsx.
//               JANGAN tambah dependensi ke data user/permission di sini.
// ==========================================================

import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import { FileQuestion, RefreshCw, ServerCrash, ShieldAlert } from 'lucide-react';

interface ErrorPageProps {
    status: 403 | 404 | 500;
}

// SUMBER: konten per status -- HANYA 3 kode yang diminta Boss (403/404/500).
// Kode lain (419/429/503 dst) TIDAK ditangani di sini, tetap fallback default
// Laravel (lihat guard status di bootstrap/app.php).
const CONTENT: Record<403 | 404 | 500, { icon: typeof ShieldAlert; title: string; description: string }> = {
    403: {
        icon: ShieldAlert,
        title: 'Akses Ditolak',
        description: 'Anda tidak memiliki izin untuk mengakses halaman ini. Hubungi admin kalau merasa ini keliru.',
    },
    404: {
        icon: FileQuestion,
        title: 'Halaman Tidak Ditemukan',
        description: 'Halaman yang Anda cari tidak ada, sudah dipindahkan, atau URL-nya salah ketik.',
    },
    500: {
        icon: ServerCrash,
        title: 'Terjadi Kesalahan Server',
        description: 'Ada yang tidak beres di sisi kami. Tim teknis sudah diberi tahu -- silakan coba lagi sebentar lagi.',
    },
};

export default function ErrorPage({ status }: ErrorPageProps) {
    const { icon: Icon, title, description } = CONTENT[status];

    return (
        <>
            <Head title={`${status} — ${title}`} />
            <div className="flex min-h-svh flex-col items-center justify-center gap-8 bg-background p-6 md:p-10">
                <Link href={route('home')} className="flex flex-col items-center gap-2 font-medium">
                    <div className="mb-1 flex h-9 w-9 items-center justify-center rounded-md">
                        <AppLogoIcon className="size-9 fill-current text-foreground dark:text-white" />
                    </div>
                    <span className="sr-only">Kembali ke beranda</span>
                </Link>

                <div className="flex w-full max-w-sm flex-col items-center gap-6 text-center">
                    <div className="flex h-16 w-16 items-center justify-center rounded-full bg-destructive/10">
                        <Icon className="size-8 text-destructive" strokeWidth={1.75} />
                    </div>

                    <div className="space-y-2">
                        <p className="text-sm font-semibold tracking-widest text-muted-foreground">ERROR {status}</p>
                        <h1 className="text-2xl font-semibold">{title}</h1>
                        <p className="text-sm text-muted-foreground">{description}</p>
                    </div>

                    <div className="flex w-full flex-col gap-2 sm:flex-row sm:justify-center">
                        <Button asChild>
                            <Link href={route('home')}>Kembali ke Beranda</Link>
                        </Button>
                        {status === 500 && (
                            <Button variant="outline" type="button" onClick={() => window.location.reload()}>
                                <RefreshCw className="size-4" />
                                Muat Ulang
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

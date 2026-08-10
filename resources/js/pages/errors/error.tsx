// ==========================================================
// MODUL       : errors/error
// KLASIFIKASI : UI
// TUJUAN      : Permintaan Boss (2026-08-07) — halaman error 403/404/500
//               profesional, gantikan halaman default Laravel/Symfony yang
//               polos. SATU komponen generik (bukan 1 file per status) --
//               beda status cuma beda ikon/judul/pesan/tombol aksi, struktur
//               visual identik, nol duplikasi markup. Dipoles ulang
//               (permintaan Boss 2026-08-09): logo brand DINAMIS (F-142,
//               bukan lagi AppLogoIcon statis -- ikut logo/nama Boss yang
//               di-setel di Setelan) + entrance animation halus (framer-motion,
//               dependency existing F-156, NOL dependency baru) + angka status
//               raksasa sebagai elemen visual, pola sama welcome.tsx (footer
//               nama perusahaan, token --tempo-*).
// DIPANGGIL   : bootstrap/app.php withExceptions() -> Inertia::render('errors/error', ['status' => ...])
// MEMANGGIL   : route('home') (tombol "Kembali ke Beranda"), AppLogo (F-142)
// DATA MASUK  : status (403 | 404 | 500) dari backend + SharedData.branding
//               (F-142, dishare GLOBAL oleh HandleInertiaRequests TERLEPAS
//               dari status login -- null saat guest/org belum isi Setelan,
//               AppLogo sendiri yang fallback ke logo default TEMPO)
// DATA KELUAR : navigasi (Link ke beranda) atau reload penuh (tombol 500)
// RISIKO      : Halaman ini bisa dirender TANPA user login (403 dari middleware
//               auth, atau 404 sebelum autentikasi) -- SENGAJA standalone
//               (tanpa AppLayout/sidebar), pola sama auth-simple-layout.tsx.
//               JANGAN tambah dependensi ke data USER/PERMISSION di sini
//               (branding org TIDAK termasuk -- sudah aman diakses guest,
//               lihat DATA MASUK).
// ==========================================================

import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
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
            <div className="relative flex min-h-svh flex-col items-center justify-center gap-8 overflow-hidden bg-background p-6 md:p-10">
                {/* SUMBER: angka status raksasa sebagai elemen visual latar (permintaan
                    Boss 2026-08-09, lihat header file) -- MURNI dekoratif (aria-hidden),
                    opacity rendah supaya tidak bersaing dengan konten di atasnya. */}
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 flex items-center justify-center text-[16rem] leading-none font-black text-foreground/5 select-none sm:text-[22rem]"
                >
                    {status}
                </span>

                <Link href={route('home')} className="relative flex flex-col items-center gap-2 font-medium">
                    <AppLogo />
                    <span className="sr-only">Kembali ke beranda</span>
                </Link>

                {/* Entrance animation halus (permintaan Boss 2026-08-09, framer-motion
                    -- dependency existing F-156) -- pola SAMA baris tabel tasks/all.tsx
                    (fade+naik sedikit), durasi dipanjangkan karena ini hero 1 elemen,
                    bukan daftar banyak baris. */}
                <motion.div
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.35, ease: 'easeOut' }}
                    className="relative flex w-full max-w-sm flex-col items-center gap-6 text-center"
                >
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
                </motion.div>
            </div>
        </>
    );
}

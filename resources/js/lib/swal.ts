// ==========================================================
// MODUL       : lib/swal
// KLASIFIKASI : UTIL
// TUJUAN      : Satu sumber pembungkus SweetAlert2 (permintaan Boss, ganti
//               alert()/confirm()/prompt() browser native di 12 file) — TIDAK
//               PERNAH styling SweetAlert2 default (pink/biru bawaan), reuse
//               `buttonVariants` (F-144-style: token/komponen yang SAMA dipakai
//               Button biasa, bukan warna hardcode kedua) supaya popup terlihat
//               menyatu dengan app, bukan seperti library asing yang ditempel.
// DIPANGGIL   : task-attachments.tsx, task-checklist.tsx, task-comments.tsx,
//               task-status-cell.tsx, extensions/index.tsx, holidays/index.tsx,
//               projects/index.tsx, roles/index.tsx, task-statuses/index.tsx,
//               tasks/index.tsx, tasks/show.tsx, users/index.tsx
// MEMANGGIL   : sweetalert2 (Swal.fire), buttonVariants (cva class, BUKAN warna baru)
// DATA MASUK  : Pesan konfirmasi/error/prompt per pemanggil
// DATA KELUAR : Promise<boolean> (confirmAction), Promise<void> (showError),
//               Promise<string|null> (promptInput) — SEMUA async, pemanggil
//               WAJIB `await` (beda dari confirm()/prompt() native yang sinkron
//               blocking — guard `if (!confirm(...)) return;` HARUS jadi
//               `if (!(await confirmAction(...))) return;` di fungsi async)
// RISIKO      : SUMBER : `buttonsStyling: false` WAJIB di setiap fire() —
//               tanpa ini SweetAlert2 pasang CSS tombol bawaannya SENDIRI di
//               atas class Tailwind kita (dua sistem tombol tabrakan visual).
// ==========================================================

import { buttonVariants } from '@/components/ui/button';
import Swal, { type SweetAlertIcon } from 'sweetalert2';

function customClass(confirmVariant: 'default' | 'destructive' = 'default') {
    return {
        popup: 'rounded-lg border bg-card text-card-foreground shadow-lg',
        title: 'text-foreground',
        htmlContainer: 'text-muted-foreground',
        actions: 'gap-2',
        confirmButton: buttonVariants({ variant: confirmVariant }),
        cancelButton: buttonVariants({ variant: 'outline' }),
    };
}

/**
 * KONTRAK: pengganti `confirm(message)` native — SATU-SATUNYA beda, hasilnya
 * Promise (WAJIB await), bukan boolean langsung.
 */
export async function confirmAction(
    message: string,
    options?: { title?: string; confirmText?: string; danger?: boolean; icon?: SweetAlertIcon },
): Promise<boolean> {
    const result = await Swal.fire({
        title: options?.title ?? 'Konfirmasi',
        text: message,
        icon: options?.icon ?? (options?.danger ? 'warning' : 'question'),
        showCancelButton: true,
        confirmButtonText: options?.confirmText ?? 'Ya',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        buttonsStyling: false,
        customClass: customClass(options?.danger ? 'destructive' : 'default'),
    });

    return result.isConfirmed;
}

/**
 * KONTRAK: pengganti `alert(message)` native untuk pesan ERROR — fire-and-forget
 * aman dipanggil TANPA await (dipakai di dalam callback onError yang sudah
 * berjalan async lewat Inertia sendiri).
 */
export async function showError(message: string, title = 'Gagal'): Promise<void> {
    await Swal.fire({
        title,
        text: message,
        icon: 'error',
        confirmButtonText: 'OK',
        buttonsStyling: false,
        customClass: customClass('default'),
    });
}

/**
 * KONTRAK: pengganti `prompt(message)` native. `null` = user membatalkan/tidak
 * mengisi (pola sama prompt() native yang return null saat Cancel).
 */
export async function promptInput(
    message: string,
    options?: {
        title?: string;
        inputType?: 'text' | 'number' | 'textarea';
        placeholder?: string;
        validator?: (value: string) => string | null | undefined;
    },
): Promise<string | null> {
    const result = await Swal.fire({
        title: options?.title ?? message,
        input: options?.inputType ?? 'text',
        inputPlaceholder: options?.placeholder,
        showCancelButton: true,
        confirmButtonText: 'Kirim',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        buttonsStyling: false,
        customClass: customClass('default'),
        inputValidator: options?.validator ? (value: string) => options.validator!(value) : undefined,
    });

    if (!result.isConfirmed) return null;

    return (result.value as string) ?? null;
}

import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

// KONTRAK: shadcn/Radix `Select` (beda dari <select> HTML) MELARANG SelectItem
// dengan value="" (throw error runtime) -- dipakai SATU sentinel ini di semua
// dropdown filter "Semua ..." (user/project/dst), dipetakan balik ke null saat
// dikirim ke filter. Bukan ID/value asli mana pun (id user/project selalu angka).
export const SELECT_ALL_VALUE = '__all__';

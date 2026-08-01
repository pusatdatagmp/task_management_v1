// ==========================================================
// MODUL       : lib/theme-tokens
// KLASIFIKASI : UTIL
// TUJUAN      : Satu sumber token editable + mekanisme apply/reset (F-143/F-144,
//               v1.2 DS-3) — dipakai BERSAMA oleh app.tsx (apply-at-load, pola
//               sama initializeTheme() appearance) dan org-settings/index.tsx
//               (live preview + Simpan + Batal + Reset). TOKEN_KEYS SENGAJA cuma
//               8 yang benar-benar dipakai komponen (lihat app.css) -- accent/
//               emerald/rose/tx3 belum dikait ke komponen mana pun (keputusan
//               Boss LANGKAH 0), diedit di sini = kelihatan "rusak" (nol efek).
// DIPANGGIL   : app.tsx, org-settings/index.tsx
// MEMANGGIL   : document.documentElement.style (CSS custom property, BUKAN
//               ubah file/class per-komponen — F-144)
// DATA MASUK  : ThemeConfig dari shared prop `theme` (HandleInertiaRequests)
//               atau draft state lokal (live preview sebelum Simpan)
// DATA KELUAR : Override inline pada :root (--tempo-*, --tempo-gradient)
// RISIKO      : SUMBER : applyThemeTokens dipanggil dengan `null`/undefined value
//               per key HARUS removeProperty (bukan set string 'null') -- kalau
//               tidak, browser membaca literal string "null" sebagai warna CSS
//               tak valid dan token itu DIAM-DIAM gagal ter-override tanpa error.
// ==========================================================

export interface ThemeToken {
    key: TokenKey;
    cssVar: string;
    label: string;
    /** Nilai default TEMPO (F-145) — dipakai tombol "Reset ke default". */
    defaultHex: string;
    hint: string;
}

export type TokenKey = 'sidebar_bg' | 'ink' | 'ink2' | 'paper' | 'card' | 'amber' | 'tx' | 'tx2';

export type GradientDirection = 'to right' | 'to bottom' | 'to bottom right';

// SUMBER: `[key: string]: string | boolean` (walau field bernama sudah cover
// semua pemakaian) -- WAJIB supaya tipe ini structurally cocok dengan
// FormDataConvertible Inertia (Record<string, FormDataConvertible>) saat
// dipakai sebagai initial value useForm() di org-settings/index.tsx. Interface
// TANPA index signature ini gagal generic constraint check useForm walau semua
// field-nya sendiri primitif valid (quirk TypeScript, bukan bug logika).
export interface GradientConfig {
    enabled: boolean;
    from: string;
    to: string;
    direction: GradientDirection;
    [key: string]: string | boolean;
}

export interface ThemeConfig {
    // SUMBER: Record<string, string> (BUKAN Partial<Record<TokenKey,string>>) --
    // alasan sama GradientConfig di atas (index signature utk useForm). Dijaga
    // type-safe di titik PAKAI (TEMPO_TOKENS.map(token => tokens[token.key])),
    // bukan di titik deklarasi.
    tokens: Record<string, string>;
    gradient: GradientConfig | null;
}

// SUMBER: daftar + default hex HARUS sama persis dengan :root di app.css --
// dipakai juga sebagai nilai awal color-picker sebelum org pernah kustom.
export const TEMPO_TOKENS: ThemeToken[] = [
    { key: 'sidebar_bg', cssVar: '--tempo-sidebar-bg', label: 'Latar Sidebar', defaultHex: '#0f1523', hint: 'Background sidebar kiri saja (F-143 — dipisah dari warna teks, DS-3).' },
    { key: 'ink', cssVar: '--tempo-ink', label: 'Warna Teks Utama', defaultHex: '#0f1523', hint: 'Teks di seluruh halaman terang + teks di atas tombol amber.' },
    { key: 'ink2', cssVar: '--tempo-ink2', label: 'Aksen Sidebar', defaultHex: '#161d30', hint: 'Warna hover/border item sidebar.' },
    { key: 'paper', cssVar: '--tempo-paper', label: 'Latar Workspace', defaultHex: '#f5f6f9', hint: 'Background area kerja di kanan sidebar.' },
    { key: 'card', cssVar: '--tempo-card', label: 'Latar Kartu', defaultHex: '#ffffff', hint: 'Background card/panel di seluruh halaman.' },
    { key: 'amber', cssVar: '--tempo-amber', label: 'Warna Aksi (Primary)', defaultHex: '#e0a012', hint: 'Tombol utama + aksen sidebar aktif (F-145 default amber).' },
    { key: 'tx', cssVar: '--tempo-tx', label: 'Teks Sidebar (aktif)', defaultHex: '#f8fafc', hint: 'Warna teks sidebar saat item di-hover/aktif.' },
    { key: 'tx2', cssVar: '--tempo-tx2', label: 'Teks Sidebar (default)', defaultHex: '#cbd5e1', hint: 'Warna teks sidebar dalam keadaan biasa.' },
];

export const GRADIENT_DIRECTIONS: { value: GradientDirection; label: string }[] = [
    { value: 'to right', label: 'Kiri → Kanan' },
    { value: 'to bottom', label: 'Atas → Bawah' },
    { value: 'to bottom right', label: 'Diagonal (kiri-atas → kanan-bawah)' },
];

export const DEFAULT_GRADIENT: GradientConfig = { enabled: false, from: '#e0a012', to: '#161d30', direction: 'to right' };

/**
 * KONTRAK: terapkan override token + gradasi ke :root LANGSUNG (inline style,
 * menang atas stylesheet tanpa perlu !important) — dipakai app.tsx (sekali saat
 * boot) DAN org-settings/index.tsx (tiap kali picker berubah, live preview).
 * `config` null/kosong = HAPUS semua override (removeProperty), CSS default
 * app.css yang berlaku (F-145 fallback aman).
 */
export function applyThemeTokens(config: ThemeConfig | null | undefined): void {
    const root = document.documentElement.style;

    for (const token of TEMPO_TOKENS) {
        const value = config?.tokens?.[token.key];

        if (value) {
            root.setProperty(token.cssVar, value);
        } else {
            root.removeProperty(token.cssVar);
        }
    }

    if (config?.gradient?.enabled) {
        root.setProperty('--tempo-gradient', `linear-gradient(${config.gradient.direction}, ${config.gradient.from}, ${config.gradient.to})`);
    } else {
        root.removeProperty('--tempo-gradient');
    }
}

export function resetThemeTokens(): void {
    applyThemeTokens(null);
}

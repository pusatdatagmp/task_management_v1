// ==========================================================
// MODUL       : org-settings/index
// KLASIFIKASI : UI
// TUJUAN      : Halaman "Setelan" org-level — tab Branding (F-142, v1.2 DS-2) +
//               tab Tema (F-143, v1.2 DS-3, token+gradasi). 1 URL, tab
//               client-side (useState) — BUKAN shadcn <Tabs>, @radix-ui/react-tabs
//               belum jadi dependency project ini (cek package.json), pasang
//               primitive baru tanpa approval eksplisit melanggar aturan proyek.
// DIPANGGIL   : SettingsController::edit()
// MEMANGGIL   : route('settings.branding.update') — POST (file logo opsional,
//               PHP tak parse multipart di method PATCH/PUT tanpa method-spoofing).
//               route('settings.theme.update') — POST token+gradasi.
//               lib/theme-tokens (applyThemeTokens, F-144 — editor ubah TOKEN,
//               komponen mewarisi via CSS var, TIDAK PERNAH edit warna langsung).
// DATA MASUK  : branding {..., logo_url} + theme {tokens, gradient} — keduanya
//               null-able, org belum tentu pernah isi (fallback default TEMPO)
// DATA KELUAR : POST FormData -> organizations.* (branding), organizations.
//               theme_config (tema)
// RISIKO      : SUMBER (Branding): preview logo pilihan BARU pakai
//               URL.createObjectURL — WAJIB revokeObjectURL saat file diganti/
//               unmount, atau blob URL bocor memory selama tab browser terbuka.
//               SUMBER (Tema): live preview men-set CSS var LANGSUNG di :root
//               tiap picker berubah (lihat ThemeTab) — WAJIB di-reset ke nilai
//               tersimpan server saat pindah tab/unmount TANPA Simpan, atau
//               draft yang batal keliru terlihat permanen sampai reload.
// ==========================================================

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { applyThemeTokens, DEFAULT_GRADIENT, GRADIENT_DIRECTIONS, TEMPO_TOKENS, type GradientConfig, type ThemeConfig, type TokenKey } from '@/lib/theme-tokens';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

interface Branding {
    company_name: string | null;
    address: string | null;
    wa_number: string | null;
    facebook_url: string | null;
    instagram_url: string | null;
    linkedin_url: string | null;
    logo_url: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Setelan', href: '/pengaturan/setelan' }];

type TabKey = 'branding' | 'tema';

export default function OrgSettingsIndex({ branding, theme }: { branding: Branding; theme: ThemeConfig | null }) {
    const [activeTab, setActiveTab] = useState<TabKey>('branding');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Setelan" />

            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Setelan</h1>

                {/* Tab manual (bukan shadcn <Tabs>, lihat header file). */}
                <div className="flex gap-1 border-b">
                    <button
                        type="button"
                        onClick={() => setActiveTab('branding')}
                        className={`border-b-2 px-3 py-2 text-sm font-medium ${
                            activeTab === 'branding' ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground'
                        }`}
                    >
                        Branding
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('tema')}
                        className={`border-b-2 px-3 py-2 text-sm font-medium ${
                            activeTab === 'tema' ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground'
                        }`}
                    >
                        Tema
                    </button>
                </div>

                {activeTab === 'branding' && <BrandingTab branding={branding} />}
                {activeTab === 'tema' && <ThemeTab theme={theme} />}
            </div>
        </AppLayout>
    );
}

function BrandingTab({ branding }: { branding: Branding }) {
    const { data, setData, post, errors, processing, recentlySuccessful } = useForm<{
        company_name: string;
        address: string;
        wa_number: string;
        facebook_url: string;
        instagram_url: string;
        linkedin_url: string;
        logo: File | null;
    }>({
        company_name: branding.company_name ?? '',
        address: branding.address ?? '',
        wa_number: branding.wa_number ?? '',
        facebook_url: branding.facebook_url ?? '',
        instagram_url: branding.instagram_url ?? '',
        linkedin_url: branding.linkedin_url ?? '',
        logo: null,
    });

    const fileInputRef = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    // RISIKO (lihat header file): blob URL WAJIB direvoke tiap kali file baru
    // dipilih ATAU komponen unmount -- kalau tidak, tiap ganti file bocor memory
    // selama tab browser terbuka.
    useEffect(() => {
        if (!data.logo) {
            setPreviewUrl(null);
            return;
        }

        const url = URL.createObjectURL(data.logo);
        setPreviewUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [data.logo]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('settings.branding.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setData('logo', null);
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    };

    return (
        <Card className="max-w-2xl">
            <CardHeader>
                <CardTitle>Branding</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-6">
                    <HeadingSmall
                        title="Identitas perusahaan"
                        description="Ganti logo & nama yang tampil di sidebar, plus kontak yang tampil di footer."
                    />

                    <div className="grid gap-2">
                        <Label htmlFor="logo">Logo</Label>
                        <div className="flex items-center gap-4">
                            {(previewUrl ?? branding.logo_url) && (
                                <img
                                    src={previewUrl ?? branding.logo_url ?? undefined}
                                    alt="Logo saat ini"
                                    className="size-16 rounded-md border object-contain p-1"
                                />
                            )}
                            <input
                                ref={fileInputRef}
                                id="logo"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                className="text-sm"
                                onChange={(e) => setData('logo', e.target.files?.[0] ?? null)}
                            />
                        </div>
                        <InputError message={errors.logo} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="company_name">Nama perusahaan</Label>
                        <Input
                            id="company_name"
                            value={data.company_name}
                            onChange={(e) => setData('company_name', e.target.value)}
                            placeholder="Kosongkan untuk pakai default TEMPO"
                        />
                        <InputError message={errors.company_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="address">Alamat</Label>
                        <Textarea id="address" value={data.address} onChange={(e) => setData('address', e.target.value)} rows={3} />
                        <InputError message={errors.address} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="wa_number">Nomor WhatsApp</Label>
                        <Input
                            id="wa_number"
                            value={data.wa_number}
                            onChange={(e) => setData('wa_number', e.target.value)}
                            placeholder="628123456789 (tanpa spasi/tanda)"
                        />
                        <InputError message={errors.wa_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="facebook_url">Facebook</Label>
                        <Input
                            id="facebook_url"
                            type="url"
                            value={data.facebook_url}
                            onChange={(e) => setData('facebook_url', e.target.value)}
                            placeholder="https://facebook.com/..."
                        />
                        <InputError message={errors.facebook_url} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="instagram_url">Instagram</Label>
                        <Input
                            id="instagram_url"
                            type="url"
                            value={data.instagram_url}
                            onChange={(e) => setData('instagram_url', e.target.value)}
                            placeholder="https://instagram.com/..."
                        />
                        <InputError message={errors.instagram_url} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="linkedin_url">LinkedIn</Label>
                        <Input
                            id="linkedin_url"
                            type="url"
                            value={data.linkedin_url}
                            onChange={(e) => setData('linkedin_url', e.target.value)}
                            placeholder="https://linkedin.com/..."
                        />
                        <InputError message={errors.linkedin_url} />
                    </div>

                    <div className="flex items-center gap-4">
                        <Button disabled={processing}>Simpan</Button>

                        <Transition show={recentlySuccessful} enter="transition ease-in-out" enterFrom="opacity-0" leave="transition ease-in-out" leaveTo="opacity-0">
                            <p className="text-sm text-muted-foreground">Tersimpan</p>
                        </Transition>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function ThemeTab({ theme }: { theme: ThemeConfig | null }) {
    // SUMBER: TANPA anotasi tipe/generic eksplisit -- inline object literal
    // (pola sama task-templates/create.tsx useForm({...})), supaya TypeScript
    // infer "fresh" object type yang lolos constraint FormDataType Inertia.
    // Interface bernama (mis. ThemeDraft) TIDAK punya index signature dan
    // GAGAL constraint check kalau dipakai sebagai anotasi/generic eksplisit di sini.
    const { data, setData, post, processing, recentlySuccessful, errors, reset, setDefaults } = useForm({
        tokens: theme?.tokens ?? {},
        gradient: theme?.gradient ?? DEFAULT_GRADIENT,
    });
    const formErrors = errors as unknown as Record<string, string | undefined>;

    // RISIKO (lihat header file): baseline "diketahui benar" untuk cleanup saat
    // unmount TANPA Simpan (pindah tab/halaman) -- diperbarui ke `data` SETELAH
    // Simpan berhasil (bukan cuma nilai awal mount), supaya tab lain tidak
    // "mewarisi" draft yang batal ATAUPUN kehilangan hasil simpan barusan.
    const lastKnownGoodRef = useRef(data);

    // Live preview (F-143): SETIAP perubahan draft langsung terlihat, sebelum Simpan.
    useEffect(() => {
        applyThemeTokens(data);
    }, [data]);

    // Unmount safety net: pindah ke tab Branding/keluar halaman TANPA Simpan
    // TIDAK boleh meninggalkan draft warna nempel di :root untuk halaman lain.
    useEffect(() => {
        return () => applyThemeTokens(lastKnownGoodRef.current);
         
    }, []);

    const setToken = (key: TokenKey, value: string) => {
        setData('tokens', { ...data.tokens, [key]: value });
    };

    const setGradientField = <K extends keyof GradientConfig>(key: K, value: GradientConfig[K]) => {
        setData('gradient', { ...data.gradient, [key]: value });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('settings.theme.update'), {
            preserveScroll: true,
            onSuccess: () => {
                lastKnownGoodRef.current = data;
                setDefaults();
            },
        });
    };

    const batal = () => reset();

    const resetDefault = () => setData({ tokens: {}, gradient: DEFAULT_GRADIENT });

    return (
        <Card className="max-w-2xl">
            <CardHeader>
                <CardTitle>Tema</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-6">
                    <HeadingSmall
                        title="Token warna"
                        description="Ubah nilai token inti — semua komponen bersama (tombol, kartu, sidebar) otomatis mewarisi, bukan diedit satu-satu (F-144)."
                    />

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {TEMPO_TOKENS.map((token) => {
                            const value = data.tokens[token.key] ?? token.defaultHex;

                            return (
                                <div key={token.key} className="grid gap-1">
                                    <Label htmlFor={`token-${token.key}`}>{token.label}</Label>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="color"
                                            id={`token-${token.key}`}
                                            value={value}
                                            onChange={(e) => setToken(token.key, e.target.value)}
                                            className="h-9 w-12 shrink-0 cursor-pointer rounded border"
                                        />
                                        <Input value={value} onChange={(e) => setToken(token.key, e.target.value)} className="font-mono text-xs" />
                                    </div>
                                    <p className="text-xs text-muted-foreground">{token.hint}</p>
                                    <InputError message={formErrors[`tokens.${token.key}`]} />
                                </div>
                            );
                        })}
                    </div>

                    <HeadingSmall title="Gradasi" description="Opsional — diterapkan ke tombol utama & sidebar sekaligus (bukan per-elemen)." />

                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={data.gradient.enabled}
                            onChange={(e) => setGradientField('enabled', e.target.checked)}
                        />
                        Aktifkan gradasi
                    </label>

                    {data.gradient.enabled && (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div className="grid gap-1">
                                <Label>Warna awal</Label>
                                <input
                                    type="color"
                                    value={data.gradient.from}
                                    onChange={(e) => setGradientField('from', e.target.value)}
                                    className="h-9 w-full cursor-pointer rounded border"
                                />
                                <InputError message={formErrors['gradient.from']} />
                            </div>
                            <div className="grid gap-1">
                                <Label>Warna akhir</Label>
                                <input
                                    type="color"
                                    value={data.gradient.to}
                                    onChange={(e) => setGradientField('to', e.target.value)}
                                    className="h-9 w-full cursor-pointer rounded border"
                                />
                                <InputError message={formErrors['gradient.to']} />
                            </div>
                            <div className="grid gap-1">
                                <Label>Arah</Label>
                                <select
                                    className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                    value={data.gradient.direction}
                                    onChange={(e) => setGradientField('direction', e.target.value as GradientConfig['direction'])}
                                >
                                    {GRADIENT_DIRECTIONS.map((d) => (
                                        <option key={d.value} value={d.value}>
                                            {d.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    )}

                    <div className="flex flex-wrap items-center gap-3">
                        <Button disabled={processing}>Simpan</Button>
                        <Button type="button" variant="outline" onClick={batal}>
                            Batal
                        </Button>
                        <Button type="button" variant="ghost" onClick={resetDefault}>
                            Reset ke default TEMPO
                        </Button>

                        <Transition show={recentlySuccessful} enter="transition ease-in-out" enterFrom="opacity-0" leave="transition ease-in-out" leaveTo="opacity-0">
                            <p className="text-sm text-muted-foreground">Tersimpan</p>
                        </Transition>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

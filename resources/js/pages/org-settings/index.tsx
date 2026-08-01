// ==========================================================
// MODUL       : org-settings/index
// KLASIFIKASI : UI
// TUJUAN      : Halaman "Setelan" org-level (F-142, v1.2 DS-2) — tab Branding
//               sekarang, slot tab Tema (DS-3) menyusul di halaman yang SAMA
//               (1 URL, tab client-side — nol route baru saat DS-3 dibangun).
//               Tab manual (useState), BUKAN shadcn <Tabs> — @radix-ui/react-tabs
//               belum jadi dependency project ini (cek package.json), pasang
//               primitive baru tanpa approval eksplisit melanggar aturan proyek.
// DIPANGGIL   : SettingsController::edit()
// MEMANGGIL   : route('settings.branding.update') — POST (file logo opsional,
//               PHP tak parse multipart di method PATCH/PUT tanpa method-spoofing)
// DATA MASUK  : branding {company_name, address, wa_number, facebook_url,
//               instagram_url, linkedin_url, logo_url} — null-able, org belum tentu
//               pernah isi (F-142, fallback default TEMPO ditangani AppLogo, BUKAN di sini)
// DATA KELUAR : POST FormData -> organizations.* (SettingsController::updateBranding)
// RISIKO      : SUMBER : preview logo pilihan BARU pakai URL.createObjectURL —
//               WAJIB revokeObjectURL saat file diganti/unmount, atau blob URL bocor
//               memory selama tab browser terbuka (cleanup di useEffect return + tiap
//               ganti file, bukan cuma unmount).
// ==========================================================

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
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

export default function OrgSettingsIndex({ branding }: { branding: Branding }) {
    const [activeTab, setActiveTab] = useState<TabKey>('branding');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Setelan" />

            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Setelan</h1>

                {/* Tab manual (bukan shadcn <Tabs>, lihat header file) -- slot Tema
                    (DS-3) sudah disiapkan di sini, tinggal isi TabsContent-nya nanti. */}
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
                        disabled
                        title="Segera — DS-3"
                        className="cursor-not-allowed border-b-2 border-transparent px-3 py-2 text-sm font-medium text-muted-foreground/50"
                    >
                        Tema (Segera)
                    </button>
                </div>

                {activeTab === 'branding' && <BrandingTab branding={branding} />}
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

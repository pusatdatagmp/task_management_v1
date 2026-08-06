// ==========================================================
// MODUL       : settings-modal
// KLASIFIKASI : UI
// TUJUAN      : Settings PERSONAL (Profile/Password/Appearance) sebagai MODAL
//               (permintaan Boss) — menggantikan 3 halaman penuh + SettingsLayout
//               yang DIPENSIUNKAN total (routes/settings.php GET dihapus, bukan
//               cuma tak dipakai). Dipicu dari dropdown user (NavUser/app-header),
//               BUKAN navigasi Inertia — dialog controlled dari komponen INDUK
//               dropdown, karena DropdownMenuContent unmount saat menutup (state
//               dialog di dalam item dropdown akan ikut hilang kalau ditaruh di situ).
// DIPANGGIL   : nav-user.tsx, app-header.tsx (parent DropdownMenu, bukan
//               UserMenuContent — lihat alasan di atas)
// MEMANGGIL   : route('profile.update'/'profile.destroy'/'password.update'),
//               DeleteUser (dialog hapus akun, nested Dialog — Radix mendukung
//               dialog bersarang), useAppearance (murni client-side, F-121)
// DATA MASUK  : auth.user (name/email dari shared prop, BUKAN prop khusus lagi —
//               mustVerifyEmail/status dari controller lama DIHAPUS, User model
//               TIDAK implements MustVerifyEmail, cabang itu selalu mati)
// DATA KELUAR : PATCH/PUT/DELETE ke endpoint mutasi yang TETAP sama (nol backend
//               logic diubah selain target redirect)
// RISIKO      : SUMBER : Dialog + DropdownMenu bersarang (Radix) — DropdownMenuItem
//               yang membuka dialog WAJIB `onSelect={(e) => { e.preventDefault(); ... }}`,
//               atau Radix mengembalikan fokus ke trigger dropdown yang sudah
//               tertutup dan menabrak fokus-trap Dialog yang baru terbuka.
// ==========================================================

import DeleteUser from '@/components/delete-user';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import AppearanceTabs from '@/components/appearance-tabs';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';

type SettingsTab = 'profile' | 'password' | 'appearance';

export default function SettingsModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
    const [activeTab, setActiveTab] = useState<SettingsTab>('profile');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] max-w-2xl overflow-y-auto">
                <DialogTitle>Settings</DialogTitle>

                <div className="flex gap-1 border-b">
                    {(
                        [
                            ['profile', 'Profile'],
                            ['password', 'Password'],
                            ['appearance', 'Appearance'],
                        ] as [SettingsTab, string][]
                    ).map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setActiveTab(key)}
                            className={`border-b-2 px-3 py-2 text-sm font-medium ${
                                activeTab === key ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {activeTab === 'profile' && <ProfileTab />}
                {activeTab === 'password' && <PasswordTab />}
                {activeTab === 'appearance' && <AppearanceTab />}
            </DialogContent>
        </Dialog>
    );
}

function ProfileTab() {
    const { auth } = usePage<SharedData>().props;
    // BUSINESS RULE (permintaan Boss): user biasa (tanpa user.manage, F-90)
    // TIDAK BOLEH ganti email sendiri -- HINT UI saja, penegakan ASLI di
    // ProfileController::update() (server selalu buang field email kalau
    // tidak punya izin, walau input ini di-enable paksa lewat devtools).
    const canChangeEmail = auth.permissions.includes('user.manage');

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: auth.user.name,
        email: auth.user.email,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'), { preserveScroll: true });
    };

    return (
        <div className="space-y-6 py-2">
            <HeadingSmall title="Profile information" description="Update your name and email address" />

            <form onSubmit={submit} className="space-y-6">
                <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoComplete="name"
                        placeholder="Full name"
                    />
                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                        placeholder="Email address"
                        disabled={!canChangeEmail}
                    />
                    {!canChangeEmail && (
                        <p className="text-sm text-muted-foreground">Hubungi admin untuk mengubah alamat email.</p>
                    )}
                    <InputError className="mt-2" message={errors.email} />
                </div>

                <div className="flex items-center gap-4">
                    <Button disabled={processing}>Save</Button>

                    <Transition show={recentlySuccessful} enter="transition ease-in-out" enterFrom="opacity-0" leave="transition ease-in-out" leaveTo="opacity-0">
                        <p className="text-sm text-neutral-600">Saved</p>
                    </Transition>
                </div>
            </form>

            <DeleteUser />
        </div>
    );
}

function PasswordTab() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <div className="space-y-6 py-2">
            <HeadingSmall title="Update password" description="Ensure your account is using a long, random password to stay secure" />

            <form onSubmit={submit} className="space-y-6">
                <div className="grid gap-2">
                    <Label htmlFor="current_password">Current password</Label>
                    <Input
                        id="current_password"
                        ref={currentPasswordInput}
                        value={data.current_password}
                        onChange={(e) => setData('current_password', e.target.value)}
                        type="password"
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        placeholder="Current password"
                    />
                    <InputError message={errors.current_password} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">New password</Label>
                    <Input
                        id="password"
                        ref={passwordInput}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        type="password"
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        placeholder="New password"
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        type="password"
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        placeholder="Confirm password"
                    />
                    <InputError message={errors.password_confirmation} />
                </div>

                <div className="flex items-center gap-4">
                    <Button disabled={processing}>Save password</Button>

                    <Transition show={recentlySuccessful} enter="transition ease-in-out" enterFrom="opacity-0" leave="transition ease-in-out" leaveTo="opacity-0">
                        <p className="text-sm text-neutral-600">Saved</p>
                    </Transition>
                </div>
            </form>
        </div>
    );
}

function AppearanceTab() {
    return (
        <div className="space-y-6 py-2">
            <HeadingSmall title="Appearance settings" description="Update your account's appearance settings" />
            <AppearanceTabs />
        </div>
    );
}

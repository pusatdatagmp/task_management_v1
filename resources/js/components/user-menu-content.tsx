import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { type User } from '@/types';
import { Link } from '@inertiajs/react';
import { LogOut, UserRoundPen } from 'lucide-react';

interface UserMenuContentProps {
    user: User;
    // SUMBER: Settings sekarang MODAL (bukan navigasi Inertia ke halaman penuh
    // yang sudah dipensiunkan) -- state open/close-nya WAJIB hidup di komponen
    // INDUK dropdown (nav-user.tsx/app-header.tsx), bukan di sini, karena
    // DropdownMenuContent unmount saat menutup (state di dalam item ikut hilang).
    onOpenSettings: () => void;
}

export function UserMenuContent({ user, onOpenSettings }: UserMenuContentProps) {
    const cleanup = useMobileNavigation();

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem
                    onSelect={(e) => {
                        // RISIKO (lihat header UserMenuContentProps): preventDefault
                        // supaya Radix tidak mengembalikan fokus ke trigger dropdown
                        // yang sudah tertutup, bentrok dengan fokus-trap Dialog baru.
                        e.preventDefault();
                        cleanup();
                        onOpenSettings();
                    }}
                >
                    <UserRoundPen className="mr-2" />
                    Edit profile
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link className="block w-full" method="post" href={route('logout')} as="button" onClick={cleanup}>
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}

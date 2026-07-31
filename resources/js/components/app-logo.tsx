import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className=" text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-md">
                {/* F-144: fill ikut --sidebar-primary-foreground (token), bukan
                    text-white/dark:text-black — sidebar TEMPO tak toggle dark/light
                    (§12.1), jadi kontras ikon tak boleh bergantung pada mode itu. */}
                <img src="/real.webp" alt="" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                {/* F-142: nama "TEMPO" placeholder statis — jadi dinamis dari
                    Branding org (DS-2, belum dibangun sesi ini). */}
                <span className="mb-0.5 truncate leading-none font-semibold">DEEVATECH</span>
            </div>
        </>
    );
}

import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { LoadingOverlay } from './components/loading-overlay';
import { initializeTheme } from './hooks/use-appearance';
import { applyThemeTokens, type ThemeConfig } from './lib/theme-tokens';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        // F-143/F-144 (v1.2 DS-3): terapkan override token org SEKALI di sini,
        // pola SAMA persis initializeTheme() appearance di bawah -- nilainya dari
        // shared prop `theme` (HandleInertiaRequests), BUKAN localStorage (tema
        // milik ORG, bukan preferensi per-browser). null/kosong = diam saja,
        // CSS default TEMPO di app.css yang berlaku (F-145 fallback aman).
        applyThemeTokens(props.initialPage.props.theme as ThemeConfig | null | undefined);

        root.render(
            <>
                <App {...props} />
                <LoadingOverlay />
            </>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

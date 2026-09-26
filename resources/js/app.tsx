import { createInertiaApp } from '@inertiajs/react';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { bootLocale } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import ReaderLayout from '@/layouts/reader-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name.startsWith('errors/'):
            case name.startsWith('public-card/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('reader/'):
                return ReaderLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    setup({ el, App, props }) {
        if (el === null) {
            return;
        }

        // The server resolved the language (App\Http\Middleware\ResolveLocale)
        // and the browser renders that decision; it never decides again.
        //
        // PAINT IN THE CALLBACK, NOT BESIDE IT. `__` from @/i18n is not
        // subscribed, so the catalog must be in the duck before the first
        // render; one that arrived after paint would repaint nothing and leave
        // Spanish readers in English. bootLocale() never rejects, and English
        // resolves at once with no request. A language change is a full
        // document reload, so this runs once per page load.
        const { locale } = props.initialPage.props;
        const root = createRoot(el);

        void bootLocale(locale.current, locale.intl).then(() => {
            root.render(
                <StrictMode>
                    <TooltipProvider delay={0}>
                        <App {...props} />
                        <Toaster />
                    </TooltipProvider>
                </StrictMode>,
            );
        });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// `<html lang>` is rendered by the server from the resolved locale. Changing
// language reloads the document, so it never needs updating here.

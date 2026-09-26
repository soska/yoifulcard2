import { createInertiaApp, router } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
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
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delay={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// The server renders `<html lang>` from the locale cookie; keep it in step
// when the language changes without a full page load.
router.on('navigate', (event) => {
    const locale = event.detail.page.props.locale;

    if (typeof locale === 'string') {
        document.documentElement.lang = locale;
    }
});

import { Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, WifiOff } from 'lucide-react';
import { useEffect } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { SuspendedBanner } from '@/components/organization/suspended-banner';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useOnline } from '@/hooks/use-online';
import { registerServiceWorker } from '@/lib/service-worker';
import { dashboard } from '@/routes';

/**
 * Full-screen, phone-first layout for the reader. No sidebar: large
 * targets, the business name, and a way back to the dashboard.
 */
export default function ReaderLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const { currentOrganization } = usePage().props;
    const online = useOnline();

    useEffect(() => {
        registerServiceWorker();
    }, []);

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <header className="flex items-center justify-between gap-3 border-b px-4 pt-[max(env(safe-area-inset-top),0.75rem)] pb-3">
                <div className="flex min-w-0 items-center gap-2">
                    <AppLogoIcon className="size-7 shrink-0 fill-current" />
                    <div className="min-w-0">
                        <p className="text-sm leading-tight font-semibold">
                            Reader
                        </p>
                        {currentOrganization && (
                            <p className="truncate text-xs text-muted-foreground">
                                {currentOrganization.name}
                            </p>
                        )}
                    </div>
                </div>
                <Button
                    variant="ghost"
                    size="lg"
                    nativeButton={false}
                    render={<Link href={dashboard()} />}
                >
                    <LayoutDashboard data-icon="inline-start" />
                    Dashboard
                </Button>
            </header>

            <div className="flex flex-col gap-2 px-4 pt-3 empty:hidden">
                <SuspendedBanner />
                {!online && (
                    <Alert>
                        <WifiOff />
                        <AlertDescription>
                            You are offline. Scans and charges need a
                            connection.
                        </AlertDescription>
                    </Alert>
                )}
            </div>

            <main className="mx-auto flex w-full max-w-lg flex-1 flex-col px-4 pt-3 pb-[max(env(safe-area-inset-bottom),1rem)]">
                {children}
            </main>
        </div>
    );
}

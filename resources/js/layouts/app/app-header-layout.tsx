import { AppContent } from '@/components/app-content';
import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
import { SuspendedBanner } from '@/components/organization/suspended-banner';
import type { AppLayoutProps } from '@/types';

export default function AppHeaderLayout({
    children,
    breadcrumbs,
}: AppLayoutProps) {
    return (
        <AppShell variant="header">
            <AppHeader breadcrumbs={breadcrumbs} />
            <AppContent variant="header">
                <SuspendedBanner className="mx-4 mt-4 w-auto" />
                {children}
            </AppContent>
        </AppShell>
    );
}

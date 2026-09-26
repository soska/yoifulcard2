import { usePage } from '@inertiajs/react';
import { Ban } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { cn } from '@/lib/utils';

export const SUSPENDED_MESSAGE = 'This business is suspended. Contact support.';

/**
 * Shown on every page while the current organization is not active. It
 * cannot be dismissed. Reads the shared `currentOrganization` prop.
 */
export function SuspendedBanner({ className }: { className?: string }) {
    const { currentOrganization } = usePage().props;

    if (!currentOrganization || currentOrganization.status === 'active') {
        return null;
    }

    return (
        <Alert variant="destructive" role="alert" className={className}>
            <Ban />
            <AlertDescription className={cn('font-medium')}>
                {SUSPENDED_MESSAGE}
            </AlertDescription>
        </Alert>
    );
}

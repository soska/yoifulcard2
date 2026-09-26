import { Badge } from '@/components/ui/badge';
import type { OrganizationStatus } from '@/types';

export const organizationStatusLabels: Record<OrganizationStatus, string> = {
    active: 'Active',
    suspended: 'Suspended',
    cancelled: 'Cancelled',
};

const variants = {
    active: 'default',
    suspended: 'destructive',
    cancelled: 'outline',
} as const satisfies Record<OrganizationStatus, string>;

export function OrganizationStatusBadge({
    status,
}: {
    status: OrganizationStatus;
}) {
    return (
        <Badge variant={variants[status]}>
            {organizationStatusLabels[status]}
        </Badge>
    );
}

import { Badge } from '@/components/ui/badge';
import { organizationStatusLabel } from '@/lib/labels';
import type { OrganizationStatus } from '@/types';

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
            {organizationStatusLabel(status)}
        </Badge>
    );
}

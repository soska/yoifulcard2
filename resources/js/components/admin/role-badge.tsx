import { Badge } from '@/components/ui/badge';
import type { MembershipRole } from '@/types';

export const roleLabels: Record<MembershipRole, string> = {
    owner: 'Owner',
    manager: 'Manager',
    employee: 'Employee',
};

export function RoleBadge({ role }: { role: MembershipRole }) {
    return (
        <Badge variant={role === 'employee' ? 'outline' : 'secondary'}>
            {roleLabels[role]}
        </Badge>
    );
}

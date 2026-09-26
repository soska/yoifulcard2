import { Badge } from '@/components/ui/badge';
import { roleLabel } from '@/lib/labels';
import type { MembershipRole } from '@/types';

export function RoleBadge({ role }: { role: MembershipRole }) {
    return (
        <Badge variant={role === 'employee' ? 'outline' : 'secondary'}>
            {roleLabel(role)}
        </Badge>
    );
}

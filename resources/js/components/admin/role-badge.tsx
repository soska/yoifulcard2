import { Badge } from '@/components/ui/badge';
import type { MembershipRole } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

export const roleLabels: Record<MembershipRole, string> = {
    owner: 'Owner',
    manager: 'Manager',
    employee: 'Employee',
};

export function RoleBadge({ role }: { role: MembershipRole }) {
    const { t } = useTranslation();

    return (
        <Badge variant={role === 'employee' ? 'outline' : 'secondary'}>
            {t(roleLabels[role])}
        </Badge>
    );
}

import { Badge } from '@/components/ui/badge';
import type { CardStatus } from '@/types';

export const cardStatusLabels: Record<CardStatus, string> = {
    active: 'Active',
    frozen: 'Frozen',
    depleted: 'Depleted',
    cancelled: 'Cancelled',
};

const variants = {
    active: 'default',
    frozen: 'secondary',
    depleted: 'outline',
    cancelled: 'destructive',
} as const satisfies Record<CardStatus, string>;

export function CardStatusBadge({ status }: { status: CardStatus }) {
    return <Badge variant={variants[status]}>{cardStatusLabels[status]}</Badge>;
}

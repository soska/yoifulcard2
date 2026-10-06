import { Badge } from '@/components/ui/badge';
import { cardStatusLabel } from '@/lib/labels';
import type { CardStatus } from '@/types';

const variants = {
    inactive: 'outline',
    active: 'default',
    frozen: 'secondary',
    depleted: 'outline',
    cancelled: 'destructive',
} as const satisfies Record<CardStatus, string>;

export function CardStatusBadge({ status }: { status: CardStatus }) {
    return <Badge variant={variants[status]}>{cardStatusLabel(status)}</Badge>;
}

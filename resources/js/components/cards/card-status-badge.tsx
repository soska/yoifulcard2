import { Badge } from '@/components/ui/badge';
import type { CardStatus } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

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
    const { t } = useTranslation();

    return (
        <Badge variant={variants[status]}>{t(cardStatusLabels[status])}</Badge>
    );
}

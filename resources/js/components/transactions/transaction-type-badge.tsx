import { Badge } from '@/components/ui/badge';
import type { TransactionType } from '@/types';

export const transactionTypeLabels: Record<TransactionType, string> = {
    load: 'Load',
    spend: 'Charge',
    adjustment: 'Adjustment',
    refund: 'Refund',
};

const variants = {
    load: 'default',
    spend: 'secondary',
    adjustment: 'outline',
    refund: 'outline',
} as const satisfies Record<TransactionType, string>;

export function TransactionTypeBadge({ type }: { type: TransactionType }) {
    return (
        <Badge variant={variants[type]}>{transactionTypeLabels[type]}</Badge>
    );
}

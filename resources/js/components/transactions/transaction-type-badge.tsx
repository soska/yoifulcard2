import { Badge } from '@/components/ui/badge';
import { transactionTypeLabel } from '@/lib/labels';
import type { TransactionType } from '@/types';

const variants = {
    load: 'default',
    spend: 'secondary',
    adjustment: 'outline',
    refund: 'outline',
} as const satisfies Record<TransactionType, string>;

export function TransactionTypeBadge({ type }: { type: TransactionType }) {
    return <Badge variant={variants[type]}>{transactionTypeLabel(type)}</Badge>;
}

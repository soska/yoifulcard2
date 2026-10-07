import { Badge } from '@/components/ui/badge';
import { transactionTypeLabel } from '@/lib/labels';
import type { TransactionType } from '@/types';

const colors = {
    load: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-300',
    spend: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300',
    adjustment: '',
    refund: '',
} as const satisfies Record<TransactionType, string>;

export function TransactionTypeBadge({ type }: { type: TransactionType }) {
    return (
        <Badge variant="outline" className={colors[type]}>
            {transactionTypeLabel(type)}
        </Badge>
    );
}

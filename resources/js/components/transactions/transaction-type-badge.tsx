import { Badge } from '@/components/ui/badge';
import type { TransactionType } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Translation keys, not English text: "Charge" is also a verb elsewhere, so
 * the type names get their own keys (see lang/en.json).
 */
export const transactionTypeLabels: Record<TransactionType, string> = {
    load: 'type.load',
    spend: 'type.spend',
    adjustment: 'type.adjustment',
    refund: 'type.refund',
};

const variants = {
    load: 'default',
    spend: 'secondary',
    adjustment: 'outline',
    refund: 'outline',
} as const satisfies Record<TransactionType, string>;

export function TransactionTypeBadge({ type }: { type: TransactionType }) {
    const { t } = useTranslation();

    return (
        <Badge variant={variants[type]}>{t(transactionTypeLabels[type])}</Badge>
    );
}

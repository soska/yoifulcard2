import type { TransactionType } from '@/types/enums.generated';

export type { TransactionType };

/** A ledger entry as pages see it. */
export type TransactionRow = {
    id: string;
    card: { id: string; code: string };
    type: TransactionType;
    /** Decimal string. Negative for an adjustment that lowered the balance. */
    amount: string;
    balance_after: string;
    note: string | null;
    performed_by: string | null;
    created_at: string | null;
};

export type TransactionFilters = {
    type: TransactionType | null;
    from: string | null;
    to: string | null;
    card: string;
};

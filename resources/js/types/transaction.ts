import type { TransactionType } from '@/types/enums.generated';

export type { TransactionType };

/** A ledger entry as pages see it. */
export type TransactionRow = {
    id: string;
    card: { id: string; code: string };
    type: TransactionType;
    /** What the entry counts. Points entries hold whole numbers. */
    unit: 'money' | 'points';
    /** Decimal string. Negative for an adjustment that lowered the balance. */
    amount: string;
    balance_after: string;
    note: string | null;
    /** The reward a redemption was for, as named when it was redeemed. */
    reward_name: string | null;
    performed_by: string | null;
    created_at: string | null;
};

export type TransactionFilters = {
    type: TransactionType | null;
    from: string | null;
    to: string | null;
    card: string;
};
